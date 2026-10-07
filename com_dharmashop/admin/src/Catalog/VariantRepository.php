<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Catalog;

use Dharma\Component\DharmaShop\Administrator\Pricing\Discount;
use Dharma\Component\DharmaShop\Administrator\Pricing\PriceResolver;
use Dharma\Component\DharmaShop\Administrator\Support\Sql;
use Joomla\CMS\Date\Date;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * Варианты товара и их цены. На этапе 1 у товара один вариант (по умолчанию).
 */
final class VariantRepository
{
    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly PriceResolver $prices,
        private readonly int $priceListId = 1,
    ) {
    }

    /** Вариант по умолчанию (с ценой основного прайс-листа) или null. */
    public function getDefault(int $productId): ?object
    {
        $priceList = $this->priceListId;
        $query = $this->db->createQuery()
            ->select('v.*, vp.price, vp.discount, vp.discount_percent, vp.discount_start, vp.discount_end, vp.price_final')
            ->from($this->db->quoteName('#__dharmashop_variants', 'v'))
            ->join('LEFT', '#__dharmashop_variant_prices AS vp ON vp.variant_id = v.id AND vp.price_list_id = :pl')
            ->where('v.product_id = :p')
            ->order('v.is_default DESC, v.ordering, v.id')
            ->bind(':pl', $priceList, ParameterType::INTEGER)
            ->bind(':p', $productId, ParameterType::INTEGER);

        return $this->db->setQuery($query, 0, 1)->loadObject() ?: null;
    }

    /**
     * Создаёт или обновляет вариант по умолчанию и его цену.
     *
     * @param   array  $data  sku, stock_tracked, backorder, weight/weight_unit, length/width/height/dimension_unit, price, discount («500» или «10%»), discount_start, discount_end (UTC)
     *
     * @return  int  id варианта
     *
     * @throws  \RuntimeException  если артикул уже занят другим вариантом
     */
    public function saveDefault(int $productId, array $data): int
    {
        $priceList = $this->priceListId;
        $existing = $this->getDefault($productId);
        $now = (new Date())->toSql();
        $sku = isset($data['sku']) && trim((string) $data['sku']) !== '' ? trim((string) $data['sku']) : null;
        $this->assertSkuAvailable($productId, $sku);

        $cols = [
            'sku' => $sku,
            'stock_tracked' => (int) ($data['stock_tracked'] ?? 0),
            'backorder' => (int) ($data['backorder'] ?? 0),
            'weight' => self::nullableNumber($data['weight'] ?? null),
            'weight_unit' => trim((string) ($data['weight_unit'] ?? 'кг')) ?: 'кг',
            'length' => self::nullableNumber($data['length'] ?? null),
            'width' => self::nullableNumber($data['width'] ?? null),
            'height' => self::nullableNumber($data['height'] ?? null),
            'dimension_unit' => trim((string) ($data['dimension_unit'] ?? 'см')) ?: 'см',
        ];

        if ($existing) {
            $variantId = (int) $existing->id;
            $obj = (object) (['id' => $variantId, 'modified' => $now] + $cols);
            $this->db->updateObject('#__dharmashop_variants', $obj, 'id', true);
        } else {
            $obj = (object) ($cols + ['product_id' => $productId, 'state' => 1, 'is_default' => 1, 'ordering' => 1, 'created' => $now]);
            $this->db->insertObject('#__dharmashop_variants', $obj, 'id');
            $variantId = (int) $obj->id;
        }

        $price = self::nullableNumber($data['price'] ?? null);
        $discount = Discount::parse($data['discount'] ?? null);
        Discount::assertValid($discount, $price);
        $start = ($data['discount_start'] ?? null) ?: null;
        $end = ($data['discount_end'] ?? null) ?: null;
        $final = $this->prices->effective($price, $discount['value'] ?? null, $discount['percent'] ?? false, $start, $end, $now);

        $sql = 'INSERT INTO #__dharmashop_variant_prices (variant_id, price_list_id, price, discount, discount_percent, discount_start, discount_end, price_final) '
            . 'VALUES (:v, :pl, :p, :d, :dp, :ds, :de, :f) '
            . 'ON DUPLICATE KEY UPDATE price = VALUES(price), discount = VALUES(discount), discount_percent = VALUES(discount_percent), '
            . 'discount_start = VALUES(discount_start), discount_end = VALUES(discount_end), price_final = VALUES(price_final)';
        Sql::exec($this->db, $sql, [
            ':v' => [$variantId, ParameterType::INTEGER],
            ':pl' => [$priceList, ParameterType::INTEGER],
            ':p' => [$price],
            ':d' => [$discount['value'] ?? null],
            ':dp' => [($discount['percent'] ?? false) ? 1 : 0, ParameterType::INTEGER],
            ':ds' => [$start],
            ':de' => [$end],
            ':f' => [$final],
        ]);

        return $variantId;
    }

    /**
     * Проверяет, что артикул не занят вариантом другого товара. Вызывается до записи карточки, чтобы ошибка не оставляла «полусохранённый» товар.
     *
     * @throws  \RuntimeException  COM_DHARMASHOP_ERROR_SKU_EXISTS
     */
    public function assertSkuAvailable(int $productId, ?string $sku): void
    {
        if ($sku === null || $sku === '') {
            return;
        }

        $query = $this->db->createQuery()->select('id')->from('#__dharmashop_variants')
            ->where('sku = :s')->where('product_id <> :p')
            ->bind(':s', $sku)->bind(':p', $productId, ParameterType::INTEGER);

        if ($this->db->setQuery($query, 0, 1)->loadResult()) {
            throw new \RuntimeException('COM_DHARMASHOP_ERROR_SKU_EXISTS');
        }
    }

    /** Удаляет все варианты товара вместе с ценами и остатками (журнал движений остаётся). */
    public function deleteForProduct(int $productId): void
    {
        $ids = $this->db->setQuery(
            $this->db->createQuery()->select('id')->from('#__dharmashop_variants')->where('product_id = :p')->bind(':p', $productId, ParameterType::INTEGER)
        )->loadColumn();

        if (!$ids) {
            return;
        }

        $in = implode(',', array_map('intval', $ids));
        $this->db->setQuery('DELETE FROM #__dharmashop_variant_prices WHERE variant_id IN (' . $in . ')')->execute();
        $this->db->setQuery('DELETE FROM #__dharmashop_stock_levels WHERE variant_id IN (' . $in . ')')->execute();
        $this->db->setQuery('DELETE FROM #__dharmashop_variants WHERE id IN (' . $in . ')')->execute();
    }

    private static function nullableNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) str_replace(',', '.', (string) $value);
    }
}
