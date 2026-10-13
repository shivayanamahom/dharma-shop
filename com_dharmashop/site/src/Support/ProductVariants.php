<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Support;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * Данные группы вариантов для структурированной разметки страницы товара (`ProductGroup` + `hasVariant`): опубликованные доступные
 * участники группы с адресом, артикулом, ценой, наличием, картинкой и значениями осей выбора. Один запрос участников и один — значений
 * осей; не больше `LIMIT` вариантов.
 */
final class ProductVariants
{
    public const LIMIT = 30;

    /**
     * @return array{name: string, id: string, varies_by: string[], variants: list<array<string, mixed>>}|null  null — товар не в группе или вариантов меньше двух
     */
    public static function forItem(DatabaseInterface $db, object $item): ?array
    {
        $groupId = (int) ($item->group_id ?? 0);

        if ($groupId < 1) {
            return null;
        }

        $levels = implode(',', array_map('intval', Factory::getApplication()->getIdentity()->getAuthorisedViewLevels()) ?: [0]);
        $group = $db->setQuery('SELECT title FROM ' . $db->quoteName('#__dharmashop_product_groups') . ' WHERE id = ' . $groupId . ' AND state = 1')->loadObject();

        if (!$group) {
            return null;
        }

        $rows = $db->setQuery(
            'SELECT p.id, p.catid, p.title, p.cover, p.price_min, p.price_max, p.price_zero_free, p.in_stock, '
            . '(SELECT v.sku FROM ' . $db->quoteName('#__dharmashop_variants', 'v') . ' WHERE v.product_id = p.id AND v.is_default = 1 AND v.state = 1 LIMIT 1) AS sku '
            . 'FROM ' . $db->quoteName('#__dharmashop_products', 'p') . ' JOIN ' . $db->quoteName('#__categories', 'c') . ' ON c.id = p.catid '
            . 'WHERE p.group_id = ' . $groupId . ' AND p.state = 1 AND c.extension = ' . $db->quote('com_dharmashop') . ' AND c.published = 1 AND c.access IN (' . $levels . ') '
            . 'AND NOT EXISTS (SELECT 1 FROM ' . $db->quoteName('#__categories', 'b') . ' WHERE b.extension = c.extension AND b.lft <= c.lft AND b.rgt >= c.rgt AND (b.published <> 1 OR b.access NOT IN (' . $levels . '))) '
            . 'ORDER BY p.id',
            0,
            self::LIMIT
        )->loadObjectList();

        if (count($rows) < 2) {
            return null;
        }

        $axes = $db->setQuery(
            'SELECT f.id, f.title, f.type FROM ' . $db->quoteName('#__dharmashop_group_axes', 'a') . ' JOIN ' . $db->quoteName('#__dharmashop_fields', 'f') . ' ON f.id = a.field_id '
            . 'WHERE a.group_id = ' . $groupId . ' AND f.state = 1 ORDER BY a.ordering, f.id'
        )->loadObjectList();
        $axisTitle = [];
        $variesBy = [];

        foreach ($axes as $axis) {
            $axisTitle[(int) $axis->id] = (string) $axis->title;
            $variesBy[] = self::schemaProperty((string) $axis->type, (string) $axis->title);
        }

        $ids = array_map(static fn (object $row): int => (int) $row->id, $rows);
        $values = [];

        if ($axisTitle) {
            foreach ($db->setQuery(
                'SELECT fv.product_id, fv.field_id, o.title FROM ' . $db->quoteName('#__dharmashop_field_values', 'fv') . ' JOIN ' . $db->quoteName('#__dharmashop_field_options', 'o') . ' ON o.id = fv.option_id '
                . 'WHERE fv.product_id IN (' . implode(',', $ids) . ') AND fv.field_id IN (' . implode(',', array_keys($axisTitle)) . ')'
            )->loadObjectList() as $value) {
                $values[(int) $value->product_id][(int) $value->field_id] = (string) $value->title;
            }
        }

        $root = rtrim(Uri::root(), '/');
        $variants = [];

        foreach ($rows as $row) {
            $route = Route::_('index.php?option=com_dharmashop&view=product&id=' . (int) $row->id, false);
            $options = [];

            foreach ($axisTitle as $fieldId => $title) {
                if (isset($values[(int) $row->id][$fieldId])) {
                    $options[$title] = $values[(int) $row->id][$fieldId];
                }
            }

            $single = $row->price_min !== null && $row->price_min !== '' && ($row->price_max === null || (float) $row->price_min === (float) $row->price_max);
            $variants[] = [
                'id' => (int) $row->id,
                'name' => (string) $row->title,
                'url' => preg_match('~^https?://~i', $route) ? $route : $root . '/' . ltrim($route, '/'),
                'sku' => (string) ($row->sku ?? ''),
                'image' => (string) (MediaUrl::absolute((string) $row->cover) ?: ''),
                'price' => $single && ((float) $row->price_min > 0 || !empty($row->price_zero_free)) ? (string) $row->price_min : null,
                'in_stock' => (int) $row->in_stock === 1,
                'options' => $options,
            ];
        }

        return ['name' => (string) $group->title, 'id' => 'group-' . $groupId, 'varies_by' => array_values(array_unique($variesBy)), 'variants' => $variants];
    }

    /** Ось выбора → свойство schema.org (цвет, размер) или её название текстом. */
    private static function schemaProperty(string $type, string $title): string
    {
        $lower = mb_strtolower($title);

        if ($type === 'color' || str_contains($lower, 'цвет') || str_contains($lower, 'color') || str_contains($lower, 'colour')) {
            return 'https://schema.org/color';
        }

        if (str_contains($lower, 'размер') || str_contains($lower, 'size')) {
            return 'https://schema.org/size';
        }

        if (str_contains($lower, 'материал') || str_contains($lower, 'material')) {
            return 'https://schema.org/material';
        }

        return $title;
    }
}
