<?php

/**
 * Dharma Shop — пакетная цена товаров.
 * @copyright (C) 2026 Dharma.
 * @license GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Pricing;

use Dharma\Component\DharmaShop\Administrator\Catalog\Indexer;
use Dharma\Component\DharmaShop\Administrator\Catalog\ProductRebuilder;
use Dharma\Component\DharmaShop\Administrator\Filter\FilterCache;
use Dharma\Component\DharmaShop\Administrator\Support\Sql;
use Joomla\CMS\Date\Date;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/** Updates the default price list for selected products and rebuilds all derived catalogue data. */
final class BatchPriceUpdater
{
    private const MAX_VALUE = 9999999999999.99;
    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly ProductRebuilder $rebuilder,
        private readonly Indexer $indexer,
    ) {
    }

    /** @return array{updated:int, skipped:int, warnings:array<int,array{title:string,key:string}>} */
    public function apply(array $productIds, array $data, User $user): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if (!$ids) { throw new \InvalidArgumentException('JGLOBAL_NO_ITEM_SELECTED'); }
        if (count($ids) > 500) { throw new \InvalidArgumentException('COM_DHARMASHOP_BATCH_PRICE_LIMIT'); }

        $priceAction = (string) ($data['price_action'] ?? 'skip');
        $discountAction = (string) ($data['discount_action'] ?? 'skip');
        $periodAction = (string) ($data['period_action'] ?? 'skip');
        if (!in_array($priceAction, ['skip', 'set', 'increase', 'decrease', 'increase_percent', 'decrease_percent'], true)
            || !in_array($discountAction, ['skip', 'set', 'clear'], true)
            || !in_array($periodAction, ['skip', 'set', 'clear'], true)) {
            throw new \InvalidArgumentException('COM_DHARMASHOP_BATCH_PRICE_INVALID');
        }
        if ($priceAction === 'skip' && $discountAction === 'skip' && $periodAction === 'skip') {
            throw new \InvalidArgumentException('JLIB_APPLICATION_ERROR_INSUFFICIENT_BATCH_INFORMATION');
        }

        $priceValue = $priceAction !== 'skip' ? self::number($data['price_value'] ?? null) : null;
        $discountValue = $discountAction === 'set' ? self::number($data['discount_value'] ?? null) : null;
        $discountPercent = (int) ($data['discount_percent'] ?? 0) === 1;
        $start = $periodAction === 'set' ? self::date($data['discount_start'] ?? '') : null;
        $end = $periodAction === 'set' ? self::date($data['discount_end'] ?? '') : null;
        if ($priceAction !== 'skip' && $priceValue < 0) { throw new \InvalidArgumentException('COM_DHARMASHOP_BATCH_PRICE_INVALID'); }
        if ($discountAction === 'set' && ($discountValue <= 0 || ($discountPercent && $discountValue >= 100))) {
            throw new \InvalidArgumentException('COM_DHARMASHOP_BATCH_PRICE_INVALID');
        }
        if ($periodAction === 'set' && $start !== null && $end !== null && $start > $end) {
            throw new \InvalidArgumentException('COM_DHARMASHOP_BATCH_PRICE_DATES');
        }
        if ($periodAction === 'set' && $start === null && $end === null) {
            throw new \InvalidArgumentException('COM_DHARMASHOP_BATCH_PRICE_DATES');
        }

        $in = implode(',', $ids);
        $rows = $this->db->setQuery(
            'SELECT p.id,p.title,p.created_by,v.id AS variant_id,vp.price,vp.discount,vp.discount_percent,vp.discount_start,vp.discount_end '
            . 'FROM #__dharmashop_products p LEFT JOIN #__dharmashop_variants v ON v.product_id=p.id AND v.is_default=1 '
            . 'LEFT JOIN #__dharmashop_variant_prices vp ON vp.variant_id=v.id AND vp.price_list_id=1 WHERE p.id IN (' . $in . ')'
        )->loadObjectList();

        $now = (new Date())->toSql();
        $resolver = new PriceResolver();
        $planned = [];
        $warnings = [];
        foreach ($rows as $row) {
            $canEdit = $user->authorise('core.edit', 'com_dharmashop.product.' . $row->id)
                || ($user->authorise('core.edit.own', 'com_dharmashop.product.' . $row->id) && (int) $row->created_by === (int) $user->id);
            if (!$canEdit) { $warnings[] = ['title' => (string) $row->title, 'key' => 'COM_DHARMASHOP_BATCH_SKIP_NO_ACCESS']; continue; }
            if (!$row->variant_id) { $warnings[] = ['title' => (string) $row->title, 'key' => 'COM_DHARMASHOP_BATCH_PRICE_NO_VARIANT']; continue; }

            $price = $row->price === null ? null : (float) $row->price;
            $discount = $row->discount === null ? null : (float) $row->discount;
            $percent = (int) $row->discount_percent === 1;
            $discountStart = $row->discount_start ?: null;
            $discountEnd = $row->discount_end ?: null;
            if ($priceAction !== 'skip') {
                if ($priceAction !== 'set' && $price === null) { $warnings[] = ['title' => (string) $row->title, 'key' => 'COM_DHARMASHOP_BATCH_PRICE_UNKNOWN']; continue; }
                $price = match ($priceAction) {
                    'set' => $priceValue,
                    'increase' => $price + $priceValue,
                    'decrease' => max(0.0, $price - $priceValue),
                    'increase_percent' => $price * (1 + $priceValue / 100),
                    'decrease_percent' => max(0.0, $price * (1 - $priceValue / 100)),
                };
                $price = round($price, 2);
                if ($price > self::MAX_VALUE) { $warnings[] = ['title' => (string) $row->title, 'key' => 'COM_DHARMASHOP_BATCH_PRICE_INVALID']; continue; }
            }
            if ($discountAction === 'clear') { $discount = null; $percent = false; }
            elseif ($discountAction === 'set') { $discount = $discountValue; $percent = $discountPercent; }
            if ($periodAction === 'clear') { $discountStart = $discountEnd = null; }
            elseif ($periodAction === 'set') { $discountStart = $start; $discountEnd = $end; }

            try {
                Discount::assertValid($discount === null ? null : ['value' => $discount, 'percent' => $percent], $price);
            } catch (\InvalidArgumentException) {
                $warnings[] = ['title' => (string) $row->title, 'key' => 'COM_DHARMASHOP_BATCH_PRICE_DISCOUNT_RANGE'];
                continue;
            }
            $final = $resolver->effective($price, $discount, $percent, $discountStart, $discountEnd, $now);
            $planned[] = [
                'product_id' => (int) $row->id,
                'variant_id' => (int) $row->variant_id,
                'price' => $price,
                'discount' => $discount,
                'discount_percent' => $percent ? 1 : 0,
                'discount_start' => $discountStart,
                'discount_end' => $discountEnd,
                'price_final' => $final,
            ];
        }

        if ($planned) {
            $this->db->transactionStart();
            try {
                foreach ($planned as $row) {
                    Sql::exec($this->db,
                        'INSERT INTO #__dharmashop_variant_prices (variant_id,price_list_id,price,discount,discount_percent,discount_start,discount_end,price_final) '
                        . 'VALUES (:variant,1,:price,:discount,:percent,:start,:end,:final) ON DUPLICATE KEY UPDATE price=VALUES(price),discount=VALUES(discount),discount_percent=VALUES(discount_percent),discount_start=VALUES(discount_start),discount_end=VALUES(discount_end),price_final=VALUES(price_final)',
                        [
                            ':variant' => [$row['variant_id'], ParameterType::INTEGER], ':price' => [$row['price']],
                            ':discount' => [$row['discount']], ':percent' => [$row['discount_percent'], ParameterType::INTEGER],
                            ':start' => [$row['discount_start']], ':end' => [$row['discount_end']], ':final' => [$row['price_final']],
                        ]
                    );
                    Sql::exec($this->db, 'UPDATE #__dharmashop_products SET modified=:modified WHERE id=:product', [
                        ':modified' => [$now], ':product' => [$row['product_id'], ParameterType::INTEGER],
                    ]);
                }
                $productIds = array_column($planned, 'product_id');
                $this->rebuilder->rebuildProducts($productIds);
                foreach (array_chunk($productIds, 500) as $chunk) { $this->indexer->refreshProducts($chunk); }
                $this->db->transactionCommit();
            } catch (\Throwable $error) {
                $this->db->transactionRollback();
                throw $error;
            }
            FilterCache::forget();
        }

        return ['updated' => count($planned), 'skipped' => count($warnings), 'warnings' => $warnings];
    }

    private static function number(mixed $value): float
    {
        $text = str_replace(',', '.', trim((string) $value));
        if ($text === '' || !preg_match('/^\d+(?:\.\d{1,2})?$/', $text)) {
            throw new \InvalidArgumentException('COM_DHARMASHOP_BATCH_PRICE_INVALID');
        }
        $number = (float) $text;
        if ($number > self::MAX_VALUE) { throw new \InvalidArgumentException('COM_DHARMASHOP_BATCH_PRICE_INVALID'); }
        return $number;
    }

    private static function date(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') { return null; }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $text);
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$date || ($errors && ($errors['warning_count'] || $errors['error_count'])) || $date->format('Y-m-d H:i:s') !== $text) {
            throw new \InvalidArgumentException('COM_DHARMASHOP_BATCH_PRICE_INVALID');
        }
        return $text;
    }
}
