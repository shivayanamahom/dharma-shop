<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\View\Updates;

use Dharma\Component\DharmaShop\Administrator\Extension\ExtensionUpdates;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

\defined('_JEXEC') or die;

/** Лёгкая страница обновлений: пакет и расширения Dharma Shop, проверка, обновление, откат (в интернет при открытии не ходит). */
class HtmlView extends BaseHtmlView
{
    protected $option = 'com_dharmashop';

    /** @var list<array<string, mixed>> */
    public array $rows = [];

    public int $stability = 4;

    public function display($tpl = null): void
    {
        if (!$this->getCurrentUser()->authorise('core.admin', 'com_dharmashop')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $updates = new ExtensionUpdates();
        $this->rows = $updates->rows();
        $this->stability = $updates->stability();

        ToolbarHelper::title(Text::_('COM_DHARMASHOP_UPDATES_TITLE'), 'sync');
        parent::display($tpl);
    }
}
