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

use Dharma\Component\DharmaShop\Site\Seo\CollectionMetadata;
use Dharma\Component\DharmaShop\Site\Seo\PageSeo;
use Dharma\Component\DharmaShop\Site\Support\Grid;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Router\Route;
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
    private int $total = 0;

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
        $this->total = $total;
        $this->pagination = $total > $limit ? new Pagination($total, intdiv($start, $limit) * $limit, $limit) : null;
        $this->setSeo($menu, $start ? intdiv($start, $limit) + 1 : 1);
        parent::display($tpl);
    }

    /** SEO списка — как у остальных страниц магазина (`PageSeo`: шаблоны настроек, пункт меню, OG, JSON-LD); адрес листа свой, индексируется. */
    private function setSeo(Registry $menu, int $page): void
    {
        $settings = ComponentHelper::getParams('com_dharmashop');
        $seo = new PageSeo($settings);
        $heading = $this->heading;
        $total = $this->total;
        $item = (object) ['title' => $heading, 'params' => new Registry(), 'metadesc' => '', 'metadata' => new Registry(), 'cover' => ''];
        $values = ['landing.title' => $heading, 'landing.category' => '', 'landing.count' => static fn (): string => (string) $total, 'landing.introtext' => ''];
        $base = Route::_('index.php?option=com_dharmashop_landing&view=landings', false, Route::TLS_IGNORE, true);
        $url = $base . ($page > 1 ? (str_contains($base, '?') ? '&' : '?') . 'start=' . (($page - 1) * $this->limit) : '');
        $meta = $seo->resolve('landing', $item, $values, $menu, $url, trim((string) $menu->get('robots', '')) ?: null, $page, false);
        $entries = [];

        foreach ($this->items as $row) {
            $entries[] = ['name' => (string) $row->title, 'url' => Route::_('index.php?option=com_dharmashop_landing&view=landing&id=' . (int) $row->id, false, Route::TLS_IGNORE, true)];
        }

        $content = (int) $settings->get('seo_structureddata', 1) === 1 ? new CollectionMetadata($heading, $entries, 'WebPage') : null;
        $seo->apply($meta, $this->getDocument(), $content);
    }
}
