<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\View\Dashboard;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * Обзор: показывает, что схема установлена, и число строк в таблицах (инструмент разработки, этап 0).
 */
class HtmlView extends BaseHtmlView
{
    protected $option = 'com_dharmashop';

    /** @var array<string, int> таблица => число строк */
    protected array $counts = [];

    public function display($tpl = null): void
    {
        $db = \Joomla\CMS\Factory::getContainer()->get(DatabaseInterface::class);
        $prefix = $db->getPrefix();

        foreach ($db->getTableList() as $table) {
            if (str_starts_with($table, $prefix . 'dharmashop_')) {
                $this->counts[substr($table, strlen($prefix))] = (int) $db->setQuery(
                    'SELECT COUNT(*) FROM ' . $db->quoteName($table)
                )->loadResult();
            }
        }

        ksort($this->counts);

        ToolbarHelper::title(Text::_('COM_DHARMASHOP_DASHBOARD_TITLE'), 'cart');

        if ($this->getCurrentUser()->authorise('core.admin', 'com_dharmashop')) {
            ToolbarHelper::preferences('com_dharmashop');
        }

        parent::display($tpl);
    }
}
