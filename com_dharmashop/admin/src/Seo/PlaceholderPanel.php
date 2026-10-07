<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Seo;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

/**
 * Справочник шорткодов в формах записей: кнопка и окно (штатное окно Joomla, по центру экрана, не зависит от прокрутки).
 * Группы — PlaceholderCatalog::describe().
 */
final class PlaceholderPanel
{
    private const DIALOG_ID = 'dharma-placeholders';

    /** Кнопка, открывающая окно со справочником. */
    public static function button(string $class = 'btn btn-sm btn-secondary'): string
    {
        Factory::getApplication()->getDocument()->getWebAssetManager()->useScript('joomla.dialog-autocreate');

        $options = json_encode(['popupType' => 'inline', 'src' => '#' . self::DIALOG_ID, 'textHeader' => Text::_('COM_DHARMASHOP_PH_TITLE'), 'width' => '900px', 'height' => 'fit-content']);

        return '<button type="button" class="' . $class . '" data-joomla-dialog="' . htmlspecialchars((string) $options, ENT_QUOTES, 'UTF-8') . '">'
            . '<span class="icon-code" aria-hidden="true"></span> ' . Text::_('COM_DHARMASHOP_PH_SHOW') . '</button>';
    }

    /**
     * Содержимое окна: <template>, которое открывает кнопка.
     *
     * @param   array<int, array{title: string, items: array<int, array{code: string, name: string, value: string}>}>  $groups
     */
    public static function dialog(array $groups): string
    {
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $html = '<template id="' . self::DIALOG_ID . '"><div class="p-3"><div><p class="text-muted mb-3">' . $e(Text::_('COM_DHARMASHOP_PH_NOTE')) . '</p>';

        foreach ($groups as $group) {
            $html .= '<table class="table table-bordered align-middle mb-4"><caption class="caption-top text-center fw-bold fs-5 pb-2">' . $e($group['title']) . '</caption>'
                . '<thead><tr><th scope="col" class="w-50">' . $e(Text::_('COM_DHARMASHOP_PH_NAME')) . '</th>'
                . '<th scope="col" class="w-25">' . $e(Text::_('COM_DHARMASHOP_PH_CODE')) . '</th>'
                . '<th scope="col" class="w-25">' . $e(Text::_('COM_DHARMASHOP_PH_NOW')) . '</th></tr></thead><tbody>';

            foreach ($group['items'] as $item) {
                $html .= '<tr><td>' . $e($item['name']) . '</td><td><code class="user-select-all">' . $e($item['code']) . '</code></td><td class="text-muted">' . $e($item['value']) . '</td></tr>';
            }

            $html .= '</tbody></table>';
        }

        return $html . '</div></div></template>';
    }
}
