<?php

/**
 * Dharma Shop — посадочные страницы (C85).
 *
 * @package    com_dharmashop_landing
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShopLanding\Site\View\Landings;

use Dharma\Component\DharmaShop\Site\Support\Grid;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** Список посадочных страниц: карточки (картинка, заголовок, краткий текст), постраничная навигация, индексируется. */
class HtmlView extends BaseHtmlView
{
    public array $items = [];
    public Registry $params;
    public ?Pagination $pagination = null;
    public string $gridClasses = '';
    public string $heading = '';
    public bool $showHeading = true;
    public bool $showImage = true;
    public bool $showIntro = true;
    private int $limit = 12;

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        $active = $app->getMenu()->getActive();
        $own = $active && $active->component === 'com_dharmashop_landing' && ($active->query['view'] ?? '') === 'landings';
        $menu = $own ? $active->getParams() : new Registry();
        $this->params = $menu;
        $this->gridClasses = Grid::classes($menu, 'landings');
        $this->showImage = (int) $menu->get('landings_show_image', 1) === 1;
        $this->showIntro = (int) $menu->get('landings_show_intro', 1) === 1;
        $this->showHeading = (int) $menu->get('show_page_heading', 1) === 1;
        $heading = trim((string) $menu->get('page_heading', ''));
        $this->heading = $heading !== '' ? $heading : ($own ? (string) $active->title : Text::_('COM_DHARMASHOP_LANDING_LIST_TITLE'));
        $limit = $this->limit = max(1, min(100, (int) $menu->get('landings_limit', 12)));
        $start = max(0, $app->getInput()->getInt('start'));
        $ordering = (string) $menu->get('landings_ordering', 'order');
        [$this->items, $total] = $this->getModel()->getPage(intdiv($start, $limit) * $limit, $limit, $ordering);
        $this->pagination = $total > $limit ? new Pagination($total, intdiv($start, $limit) * $limit, $limit) : null;
        $this->setSeo($menu, $start ? intdiv($start, $limit) + 1 : 1);
        parent::display($tpl);
    }

    /** Страница списка индексируется; адрес страницы навигации — свой, канонический — тоже свой. */
    private function setSeo(Registry $menu, int $page): void
    {
        $document = $this->getDocument();
        $suffix = $page > 1 ? ' — ' . Text::sprintf('COM_DHARMASHOP_LANDING_PAGE_N', $page) : '';
        $title = trim((string) $menu->get('page_title', '')) ?: $this->heading;
        $document->setTitle($title . $suffix);

        if (($description = trim((string) $menu->get('menu-meta_description', ''))) !== '' && $page === 1) {
            $document->setDescription($description);
        }

        $document->setMetaData('robots', trim((string) $menu->get('robots', '')) ?: 'index, follow');
        $base = Route::_('index.php?option=com_dharmashop_landing&view=landings', false);
        $url = preg_match('~^https?://~i', $base) ? $base : rtrim(Uri::root(), '/') . '/' . ltrim($base, '/');
        $document->addHeadLink($url . ($page > 1 ? '?start=' . (($page - 1) * $this->limit) : ''), 'canonical');
    }
}
