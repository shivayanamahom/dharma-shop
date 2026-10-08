<?php

/**
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma
 * @license    GNU General Public License version 2 or later
 */

namespace Dharma\Component\DharmaShop\Administrator\Seo;

use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

/**
 * Справочник шорткодов (подстановок) для форм админки: что можно писать в SEO-полях и текстах записи.
 * Список зеркалит docs/SHORTCODES.md; подписи — языковые строки COM_DHARMASHOP_PH_<ОБЛАСТЬ>_<ИМЯ>.
 */
final class PlaceholderCatalog
{
    /**
     * @param   string  $area  category, brand, badge или product
     * @param   bool    $root  true — страница корня раздела: только название и глобальные
     *
     * @return  array<int, array{label: string, items: string[]}>  группы: ключ подписи и коды подстановок
     */
    public static function groups(string $area, bool $root = false): array
    {
        $own = [$area . '.title'];

        if ($area === 'product') {
            return [
                ['label' => 'COM_DHARMASHOP_PH_GROUP_PRODUCT', 'items' => ['product.title', 'product.introtext', 'product.sku', 'product.category', 'product.brand', 'product.price', 'product.price_old', 'product.discount', 'product.unit', 'product.stock']],
                ['label' => 'COM_DHARMASHOP_PH_GROUP_GLOBAL', 'items' => ['global.sitename', 'global.currency', 'global.url']],
            ];
        }

        if ($root) {
            return [
                ['label' => 'COM_DHARMASHOP_PH_GROUP_' . strtoupper($area), 'items' => $own],
                ['label' => 'COM_DHARMASHOP_PH_GROUP_GLOBAL', 'items' => ['global.sitename', 'global.currency', 'global.url']],
            ];
        }

        if ($area === 'category') {
            array_push($own, 'category.parent', 'category.introtext');
        }

        array_push($own, $area . '.price_min', $area . '.price_max', $area . '.count');

        return [
            ['label' => 'COM_DHARMASHOP_PH_GROUP_' . strtoupper($area), 'items' => $own],
            ['label' => 'COM_DHARMASHOP_PH_GROUP_GLOBAL', 'items' => ['global.sitename', 'global.currency', 'global.url']],
        ];
    }

    /**
     * Группы для окна справочника: названия, коды и текущие значения записи.
     *
     * @param   array<string, string>  $values  значения по коду подстановки (нет значения — прочерк)
     *
     * @return  array<int, array{title: string, items: array<int, array{code: string, name: string, value: string}>}>
     */
    public static function describe(string $area, array $values, bool $root = false): array
    {
        $groups = [];

        foreach (self::groups($area, $root) as $group) {
            $items = [];

            foreach ($group['items'] as $code) {
                $items[] = ['code' => '{' . $code . '}', 'name' => Text::_(self::labelKey($code)), 'value' => (string) ($values[$code] ?? '—')];
            }

            $groups[] = ['title' => Text::_($group['label']), 'items' => $items];
        }

        return $groups;
    }

    /** Ключ языковой строки с названием подстановки. */
    public static function labelKey(string $code): string
    {
        return 'COM_DHARMASHOP_PH_' . strtoupper(str_replace('.', '_', $code));
    }
}
