<?php

/**
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma
 * @license    GNU General Public License version 2 or later
 */

namespace Dharma\Component\DharmaShop\Administrator\Seo;

\defined('_JEXEC') or die;

/**
 * Справочник шорткодов (подстановок) для форм админки: что можно писать в SEO-полях и текстах записи.
 * Список зеркалит docs/SHORTCODES.md; подписи — языковые строки COM_DHARMASHOP_PH_<ОБЛАСТЬ>_<ИМЯ>.
 */
final class PlaceholderCatalog
{
    /**
     * @param   string  $area  category, brand или badge
     * @param   bool    $root  true — страница корня раздела: только название и глобальные
     *
     * @return  array<int, array{label: string, items: string[]}>  группы: ключ подписи и коды подстановок
     */
    public static function groups(string $area, bool $root = false): array
    {
        $own = [$area . '.title'];

        if ($root) {
            return [
                ['label' => 'COM_DHARMASHOP_PH_GROUP_' . strtoupper($area), 'items' => $own],
                ['label' => 'COM_DHARMASHOP_PH_GROUP_GLOBAL', 'items' => ['global.sitename', 'global.currency']],
            ];
        }

        if ($area === 'category') {
            $own[] = 'category.parent';
        }

        array_push($own, $area . '.price_min', $area . '.price_max', $area . '.count');

        return [
            ['label' => 'COM_DHARMASHOP_PH_GROUP_' . strtoupper($area), 'items' => $own],
            ['label' => 'COM_DHARMASHOP_PH_GROUP_GLOBAL', 'items' => ['global.sitename', 'global.currency']],
        ];
    }

    /** Ключ языковой строки с названием подстановки. */
    public static function labelKey(string $code): string
    {
        return 'COM_DHARMASHOP_PH_' . strtoupper(str_replace('.', '_', $code));
    }
}
