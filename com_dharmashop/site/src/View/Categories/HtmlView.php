<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Site\View\Categories;

use Dharma\Component\DharmaShop\Site\Seo\CollectionMetadata;
use Dharma\Component\DharmaShop\Site\Seo\PageMetadata;
use Dharma\Component\DharmaShop\Site\Placeholder\CategoryValues;
use Dharma\Component\DharmaShop\Site\Placeholder\GlobalValues;
use Dharma\Component\DharmaShop\Site\Placeholder\Renderer;
use Dharma\Component\DharmaShop\Site\Seo\PageSeo;
use Dharma\Component\DharmaShop\Site\Support\MediaUrl;
use Dharma\Component\DharmaShop\Site\Support\Grid;
use Dharma\Component\DharmaShop\Site\Support\Taxonomy;
use Dharma\Component\DharmaShop\Administrator\Pricing\MoneyFormatter;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

class HtmlView extends BaseHtmlView
{
    protected $option = 'com_dharmashop';
    public object $parent;
    public array $items = [];
    public PageMetadata $seo;
    public Registry $params;
    public string $cardLayout = 'default';
    public int $columns = 3;
    public string $gridClasses = '';
    private array $cardStats = [];
    private ?MoneyFormatter $money = null;
    public bool $showHeading = true;
    public string $image = '';
    /** @var array<string, string|callable> значения подстановок страницы (SEO и тексты) */
    private array $textValues = [];

    /** Тип записей списка; бренды и бейджи переопределяют. */
    protected function taxonomy(): Taxonomy
    {
        return Taxonomy::category();
    }

    public function display($tpl = null): void
    {
        $taxonomy = $this->taxonomy();
        $app = Factory::getApplication();
        $model = $this->getModel();
        $this->parent = $model->getParent();
        $this->items = $model->getItems();
        $ancestors = $model->getAncestors();
        $settings = ComponentHelper::getParams('com_dharmashop');
        $this->params = clone $settings;
        $displayKeys = array_merge(Grid::keys($taxonomy->gridPrefix()), [$taxonomy->listLayoutKey(), $taxonomy->cardKey('layout'),
            $taxonomy->cardKey('intro'), $taxonomy->cardKey('count'), $taxonomy->cardKey('prices')]);
        foreach (array_merge($ancestors, [$this->parent]) as $ancestor) {
            foreach ($displayKeys as $key) {
                $value = $ancestor->params->get($key);
                if ($value !== null && $value !== '') {
                    $this->params->set($key, $value);
                }
            }
        }
        $active = $app->getMenu()->getActive();
        $menu = $active && $active->component === 'com_dharmashop' && ($active->query['view'] ?? '') === $taxonomy->listView
            ? $active->getParams() : new Registry();
        foreach ($displayKeys as $key) {
            $value = $menu->get($key);
            if ($value !== null && $value !== '') {
                $this->params->set($key, $value);
            }
        }
        $this->columns = max(1, min(6, (int) $this->params->get($taxonomy->gridPrefix() . '_columns', 3)));
        $this->gridClasses = Grid::classes($this->params, $taxonomy->gridPrefix());
        $showCount = (int) $this->params->get($taxonomy->cardKey('count'), 0) === 1;
        $showPrices = (int) $this->params->get($taxonomy->cardKey('prices'), 0) === 1;
        if ($showCount || $showPrices) {
            $this->cardStats = $model->getCardStats($this->items, $showPrices);
        }
        $this->money = new MoneyFormatter($settings);
        $this->cardLayout = $this->existingLayout((string) $this->params->get($taxonomy->cardKey('layout'), 'default'), 'layouts/components/dharmashop/' . $taxonomy->cardFolder());
        $this->setLayout($this->existingLayout((string) $this->params->get($taxonomy->listLayoutKey(), 'default'), 'components/com_dharmashop/tmpl/' . $taxonomy->listView));
        $this->showHeading = (int) $menu->get('show_page_heading', 1) === 1;
        $this->image = MediaUrl::absolute((string) $this->parent->params->get('image', ''));
        $this->textValues = CategoryValues::build($taxonomy->area, $this->parent, $ancestors, $model, $this->money);
        $values = $this->textValues;
        // SEO пункта меню относится к его собственной странице, не ко всем дочерним категориям.
        $menuId = max(0, (int) ($active->query['id'] ?? 0));
        $menuId = $menuId <= 1 ? 0 : $menuId;
        $ownMenu = $menuId === (int) $this->parent->id ? $menu : new Registry();
        $service = new PageSeo($settings);
        $this->seo = $service->resolve($taxonomy->area, $this->parent, $values, $ownMenu, $this->absoluteRoute((int) $this->parent->id));
        $collection = null;
        if ((int) $settings->get('seo_structureddata', 1) === 1) {
            $entries = [];
            foreach ($this->items as $item) {
                $entries[] = ['name' => (string) $item->title, 'url' => $this->absoluteRoute((int) $item->id)];
            }
            $collection = new CollectionMetadata($this->seo->h1 ?: (string) $this->parent->title, $entries);
        }
        $service->apply($this->seo, $this->getDocument(), $collection);
        $pastMenu = $menuId <= 1;
        $chain = [];
        foreach (array_merge($ancestors, $this->parent->id ? [$this->parent] : []) as $ancestor) {
            if ((int) $ancestor->id === $menuId) {
                $pastMenu = true;
            } elseif ($pastMenu) {
                $meta = $service->resolve($taxonomy->area, $ancestor, CategoryValues::build($taxonomy->area, $ancestor, $chain, $model, $this->money),
                    new Registry(), $this->absoluteRoute((int) $ancestor->id));
                $app->getPathway()->addItem($meta->breadcrumb, $this->categoryRoute((int) $ancestor->id));
            }
            $chain[] = $ancestor;
        }
        parent::display($tpl);
    }

    /** Тексты категории (интро, описание) с подстановками {category.title}, {category.price_min} и др. */
    public function text(string $html): string
    {
        return (new Renderer())->renderHtml($html, $this->textValues + GlobalValues::build(ComponentHelper::getParams('com_dharmashop')));
    }

    public function categoryRoute(int $id): string
    {
        $taxonomy = $this->taxonomy();
        $active = Factory::getApplication()->getMenu()->getActive();
        if ($active && $active->component === 'com_dharmashop' && ($active->query['view'] ?? '') === $taxonomy->listView) {
            $menuId = (int) ($active->query['id'] ?? 0);
            if (($menuId <= 1 && $id <= 1) || $menuId === $id) {
                if ($active->home) {
                    return Uri::root(true) . '/';
                }
                // У пункта меню брендов и бейджей параметр id (корень) не ключ вида: без него адрес чистый, без «?id=0».
                return $taxonomy->area === 'category'
                    ? Route::_('index.php?Itemid=' . (int) $active->id, false)
                    : Route::_('index.php?option=com_dharmashop&view=' . $taxonomy->listView . '&Itemid=' . (int) $active->id, false);
            }
        }
        // Списки брендов и бейджей плоские: карточка ведёт на страницу записи (вложенные бренды — внутри неё), у категорий вложен сам список.
        $view = $taxonomy->pageView;

        return Route::_('index.php?option=com_dharmashop&view=' . $view . '&id=' . $id, false);
    }

    public function renderCard(object $item): string
    {
        $layout = new FileLayout('components.dharmashop.' . $this->taxonomy()->cardFolder() . '.' . $this->cardLayout, null, ['component' => 'none', 'client' => 0]);
        $stats = $this->cardStats[(int) $item->id] ?? null;
        $min = $stats->price_min ?? null;
        $max = $stats->price_max ?? null;
        $range = $min === null ? '' : $this->money->format($min);
        if ($min !== null && $max !== null && (float) $min !== (float) $max) {
            $range .= ' — ' . $this->money->format($max);
        }

        return $layout->render(['item' => $item, 'url' => $this->categoryRoute((int) $item->id),
            'showIntro' => (int) $this->params->get($this->taxonomy()->cardKey('intro'), 1) === 1,
            'showCount' => (int) $this->params->get($this->taxonomy()->cardKey('count'), 0) === 1,
            'showPrices' => (int) $this->params->get($this->taxonomy()->cardKey('prices'), 0) === 1,
            'productCount' => (string) ($stats->product_count ?? '0'), 'priceRange' => $range]);
    }

    private function absoluteRoute(int $id): string
    {
        $route = $this->categoryRoute($id);
        if (preg_match('~^https?://~i', $route)) {
            return $route;
        }
        return rtrim(Uri::root(), '/') . '/' . ltrim(substr($route, strlen(Uri::root(true))), '/');
    }

    private function existingLayout(string $name, string $directory): string
    {
        return preg_match('/^[A-Za-z0-9_-]+$/D', $name) && is_file(JPATH_ROOT . '/' . $directory . '/' . $name . '.php') ? $name : 'default';
    }
}
