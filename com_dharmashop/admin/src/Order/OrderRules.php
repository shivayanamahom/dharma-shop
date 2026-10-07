<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Order;

use Dharma\Component\DharmaShop\Administrator\Support\Sql;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * Правила оформления заказа: количество (минимум, шаг, максимум), единица измерения, учёт остатков и продажа без остатка.
 *
 * Наследование: поле товара → настройки его основной категории → настройки родительских категорий (ближайшая задавшая побеждает)
 * → общие настройки магазина → встроенное значение. Пустое поле (NULL или пустая строка) означает «наследовать».
 *
 * Количество и единица вычисляются при чтении (корзина, страница товара). Учёт остатков влияет на наличие в списках,
 * поэтому его итоговое значение записывается в варианты (variants.stock_tracked / backorder): при сохранении товара,
 * при смене настроек категории и командой обслуживания.
 */
final class OrderRules
{
    public const FIELDS = ['qty_min', 'qty_step', 'qty_max', 'unit', 'stock_tracked', 'backorder'];

    private const BUILTIN = ['qty_min' => 1.0, 'qty_step' => 1.0, 'qty_max' => 0.0, 'unit' => 'шт', 'stock_tracked' => 0, 'backorder' => 0];

    /** @var array<int, array<string, mixed>>|null настройки каждой категории магазина: id => params */
    private ?array $categoryParams = null;

    public function __construct(private readonly DatabaseInterface $db, private readonly Registry $params)
    {
    }

    /** Сбрасывает кэш настроек категорий (после их изменения в этом же запросе). */
    public function flush(): void
    {
        $this->categoryParams = null;
    }

    /** Подпись источника значения: «из настроек магазина» или «из категории «…»». */
    public function sourceLabel(int|string $source): string
    {
        if (!is_int($source)) {
            return Text::_('COM_DHARMASHOP_ORDER_FROM_SETTINGS');
        }

        $title = (string) $this->db->setQuery(
            $this->db->createQuery()->select('title')->from('#__categories')->where('id = ' . $source)
        )->loadResult();

        return Text::sprintf('COM_DHARMASHOP_ORDER_FROM_CATEGORY', $title);
    }

    /** Значения из общих настроек магазина (ключи order_<поле>); не заданные — встроенные. @return array<string, float|int|string> */
    public function defaults(): array
    {
        $values = [];

        foreach (self::FIELDS as $field) {
            $raw = $this->params->get('order_' . $field);
            // Пока настройки магазина не сохранялись, действуют встроенные значения; пустая единица, заданная намеренно, остаётся пустой.
            $useBuiltin = $raw === null || ($raw === '' && $field !== 'unit');
            $values[$field] = self::cast($field, $useBuiltin ? self::BUILTIN[$field] : $raw);
        }

        return $values;
    }

    /**
     * Итоговые правила для категории: values — значения, sources — откуда взято (settings или номер категории).
     *
     * @param   bool  $includeSelf  false — без настроек самой категории (то, что она унаследует; для подсказок в её форме)
     *
     * @return  array{values: array<string, float|int|string>, sources: array<string, string|int>}
     */
    public function forCategory(int $catid, bool $includeSelf = true): array
    {
        $values = $this->defaults();
        $sources = array_fill_keys(self::FIELDS, 'settings');
        $found = [];

        foreach ($this->chain($catid) as $id => $params) {
            if (!$includeSelf && $id === $catid) {
                continue;
            }

            foreach (self::FIELDS as $field) {
                if (isset($found[$field])) {
                    continue;
                }

                $raw = $params['order_' . $field] ?? '';

                if ($raw !== '' && $raw !== null) {
                    $values[$field] = self::cast($field, $raw);
                    $sources[$field] = $id;
                    $found[$field] = true;
                }
            }
        }

        return ['values' => $values, 'sources' => $sources];
    }

    /**
     * Итоговые правила товара: его собственные значения поверх правил его основной категории.
     *
     * @param   array<string, mixed>|object  $product  нужны catid и собственные поля (unit, qty_min, qty_step, qty_max, stock_tracked, backorder)
     *
     * @return  array<string, float|int|string>
     */
    public function forProduct(array|object $product): array
    {
        $product = (array) $product;
        $rules = $this->forCategory((int) ($product['catid'] ?? 0))['values'];

        foreach (self::FIELDS as $field) {
            $own = $product[$field] ?? null;

            if ($own !== null && $own !== '') {
                $rules[$field] = self::cast($field, $own);
            }
        }

        return $rules;
    }

    /** Записывает итоговые учёт остатков и «продавать без остатка» в варианты товаров с id от $from до $to и пересчитывает наличие. */
    public function materializeRange(int $from, int $to): void
    {
        $this->materialize('p.id BETWEEN :f AND :t', [':f' => [$from, ParameterType::INTEGER], ':t' => [$to, ParameterType::INTEGER]]);
    }

    /** То же для товаров, у которых основная категория входит в список (после смены настроек категорий). @param int[] $catids */
    public function materializeCategories(array $catids): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $catids))));

        if ($ids) {
            $this->materialize('p.catid IN (' . implode(',', $ids) . ')', []);
        }
    }

    /** Основные категории магазина вместе с потомками указанных (где наследование могло измениться). @return int[] */
    public function subtree(int $catid): array
    {
        $query = Sql::query(
            $this->db,
            'SELECT c.id FROM #__categories c JOIN #__categories r ON r.id = :id AND c.extension = r.extension AND c.lft >= r.lft AND c.rgt <= r.rgt',
            [':id' => [$catid, ParameterType::INTEGER]]
        );

        return array_map('intval', $this->db->setQuery($query)->loadColumn());
    }

    private function materialize(string $where, array $params): void
    {
        $defaults = $this->defaults();
        $case = static function (string $field, array $byCategory, int $default): string {
            if (!$byCategory) {
                return (string) $default;
            }

            $sql = 'CASE p.catid';

            foreach ($byCategory as $id => $value) {
                $sql .= ' WHEN ' . (int) $id . ' THEN ' . (int) $value;
            }

            return $sql . ' ELSE ' . $default . ' END';
        };

        $tracked = $backorder = [];

        foreach (array_keys($this->allCategoryParams()) as $id) {
            $values = $this->forCategory($id)['values'];

            if ((int) $values['stock_tracked'] !== (int) $defaults['stock_tracked']) {
                $tracked[$id] = $values['stock_tracked'];
            }

            if ((int) $values['backorder'] !== (int) $defaults['backorder']) {
                $backorder[$id] = $values['backorder'];
            }
        }

        Sql::exec(
            $this->db,
            'UPDATE #__dharmashop_variants v JOIN #__dharmashop_products p ON p.id = v.product_id '
            . 'SET v.stock_tracked = COALESCE(p.stock_tracked, ' . $case('stock_tracked', $tracked, (int) $defaults['stock_tracked']) . '), '
            . 'v.backorder = COALESCE(p.backorder, ' . $case('backorder', $backorder, (int) $defaults['backorder']) . ') '
            . 'WHERE ' . $where,
            $params
        );

        Sql::exec(
            $this->db,
            'UPDATE #__dharmashop_variants v JOIN #__dharmashop_products p ON p.id = v.product_id '
            . 'SET v.in_stock = IF(v.stock_tracked = 0 OR v.stock_total > 0 OR v.backorder = 1, 1, 0) WHERE ' . $where,
            $params
        );
    }

    /** Цепочка настроек от самой категории к корню: id => params (разобранные). @return array<int, array<string, mixed>> */
    private function chain(int $catid): array
    {
        if ($catid <= 0) {
            return [];
        }

        $query = Sql::query(
            $this->db,
            'SELECT c.id, c.params FROM #__categories c JOIN #__categories s ON s.id = :id '
            . 'WHERE c.extension = s.extension AND c.lft <= s.lft AND c.rgt >= s.rgt ORDER BY c.lft DESC',
            [':id' => [$catid, ParameterType::INTEGER]]
        );
        $chain = [];

        foreach ($this->db->setQuery($query)->loadObjectList() as $row) {
            $chain[(int) $row->id] = json_decode((string) $row->params, true) ?: [];
        }

        return $chain;
    }

    /** @return array<int, array<string, mixed>> */
    private function allCategoryParams(): array
    {
        if ($this->categoryParams === null) {
            $this->categoryParams = [];
            $query = $this->db->createQuery()->select('id, params')->from('#__categories')->where('extension = ' . $this->db->quote('com_dharmashop'));

            foreach ($this->db->setQuery($query)->loadObjectList() as $row) {
                $this->categoryParams[(int) $row->id] = json_decode((string) $row->params, true) ?: [];
            }
        }

        return $this->categoryParams;
    }

    private static function cast(string $field, mixed $value): float|int|string
    {
        return match ($field) {
            'qty_min', 'qty_step', 'qty_max' => (float) str_replace(',', '.', (string) $value),
            'unit' => trim((string) $value),
            default => (int) ((string) $value === '1' || $value === 1 || $value === true),
        };
    }
}
