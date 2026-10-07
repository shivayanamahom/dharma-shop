<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\View\Category;

use Dharma\Component\DharmaShop\Administrator\Pricing\MoneyFormatter;
use Dharma\Component\DharmaShop\Site\Model\CategoryModel;
use Dharma\Component\DharmaShop\Site\Seo\PageMetadata;
use Dharma\Component\DharmaShop\Site\Seo\PageSeo;
use Dharma\Component\DharmaShop\Site\Support\Grid;
use Dharma\Component\DharmaShop\Site\Support\MediaUrl;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** Страница товаров; три варианта карточки пока намеренно пусты (C34). */
class HtmlView extends BaseHtmlView
{
    protected $option = 'com_dharmashop';
    public object $item;
    public array $items = [];
    public array $subcategories = [];
    public Registry $params;
    public PageMetadata $seo;
    public bool $showHeading = true;
    public string $image = '';
    public string $gridClasses = '';
    public string $subcategoryGridClasses = '';
    public string $cardLayout = 'grid';
    public string $productView = 'grid';
    public string $ordering = 'order';
    public array $sortOptions = [];
    public int $menuId = 0;
    public int $after = 0;
    public int $next = 0;
    public ?int $productCount = null;
    public string $priceRange = '';
    private string $subcategoryLayout = 'default';
    private array $subcategoryStats = [];
    private MoneyFormatter $money;

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        $model = $this->getModel();
        $this->item = $model->getParent();
        $this->subcategories = $model->getItems();
        $ancestors = $model->getAncestors();
        $settings = ComponentHelper::getParams('com_dharmashop');
        $this->params = clone $settings;
        $keys = array_merge(Grid::keys('category'), Grid::keys('categories'), ['category_layout', 'product_card_layout',
            'product_card_intro', 'category_product_count', 'category_price_range', 'category_card_layout',
            'category_card_intro', 'category_card_count', 'category_card_prices', 'list_limit', 'list_ordering',
            'category_default_view', 'subcategory_show_image']);
        foreach (array_merge($ancestors, [$this->item]) as $ancestor) {
            $this->mergeDisplay($ancestor->params, $keys);
        }
        $active = $app->getMenu()->getActive();
        $ownView = $active && $active->component === 'com_dharmashop' && ($active->query['view'] ?? '') === 'category';
        $menu = $ownView ? $active->getParams() : new Registry();
        $this->menuId = $ownView ? (int) $active->id : 0;
        $this->mergeDisplay($menu, $keys);
        $this->money = new MoneyFormatter($settings);
        $this->showHeading = (int) $menu->get('show_page_heading', 1) === 1;
        $this->image = MediaUrl::absolute((string) $this->item->params->get('image', ''));
        $this->setLayout($this->existingLayout((string) $this->params->get('category_layout', 'default'), 'components/com_dharmashop/tmpl/category'));
        $defaultCard = (string) $this->params->get('product_card_layout', 'grid');
        // Старый default.php остаётся совместимым именем сетки.
        $defaultCard = $defaultCard === 'default' || $defaultCard === '' ? 'grid' : $defaultCard;
        $modes = ['grid', 'list', 'table'];
        $legacyView = in_array($defaultCard, $modes, true) ? $defaultCard : 'grid';
        $defaultView = (string) $this->params->get('category_default_view', $legacyView);
        $requestedView = $app->getInput()->getCmd('product_view', '');
        $this->productView = in_array($requestedView, $modes, true) ? $requestedView
            : (in_array($defaultView, $modes, true) ? $defaultView : 'grid');
        // Стандартные файлы выбираются режимом; собственный файл получает mode в displayData.
        $this->cardLayout = $this->existingLayout(in_array($defaultCard, $modes, true) ? $this->productView : $defaultCard,
            'layouts/components/dharmashop/product-card', $this->productView);
        $this->gridClasses = Grid::classes($this->params, 'category');
        $this->subcategoryGridClasses = Grid::classes($this->params, 'categories');
        $this->subcategoryLayout = $this->existingLayout((string) $this->params->get('category_card_layout', 'default'), 'layouts/components/dharmashop/category-card');
        $this->sortOptions = [
            'order' => Text::_('COM_DHARMASHOP_SORT_ORDER'), 'title' => Text::_('COM_DHARMASHOP_SORT_TITLE'),
            'price_asc' => Text::_('COM_DHARMASHOP_SORT_PRICE_ASC'), 'price_desc' => Text::_('COM_DHARMASHOP_SORT_PRICE_DESC'),
            'new' => Text::_('COM_DHARMASHOP_SORT_NEW'),
        ];
        $ordering = $app->getInput()->getCmd('sort', (string) $this->params->get('list_ordering', 'order'));
        $this->ordering = in_array($ordering, CategoryModel::SORTS, true) ? $ordering : 'order';
        $limit = max(1, min(200, (int) $this->params->get('list_limit', 24)));
        $this->after = max(0, $app->getInput()->getInt('after'));
        $products = $model->getProducts($this->ordering, $limit, $this->after);
        $this->items = $products['items'];
        $this->next = $products['next'];
        $showCount = (int) $this->params->get('category_product_count', 0) === 1;
        $showPrices = (int) $this->params->get('category_price_range', 0) === 1;
        if ($showCount || $showPrices) {
            $stats = $model->getProductStats($showPrices);
            $this->productCount = $showCount ? (int) $stats->product_count : null;
            $this->priceRange = $showPrices ? $this->formatRange($stats->price_min, $stats->price_max) : '';
        }
        if ((int) $this->params->get('category_card_count', 0) || (int) $this->params->get('category_card_prices', 0)) {
            $this->subcategoryStats = $model->getCardStats($this->subcategories, (int) $this->params->get('category_card_prices', 0) === 1);
        }
        $seo = new PageSeo($settings);
        $menuCategory = max(0, (int) ($active->query['id'] ?? 0));
        $ownMenu = ($menuCategory <= 1 ? 0 : $menuCategory) === (int) $this->item->id ? $menu : new Registry();
        $this->seo = $seo->resolve('category', $this->item, ['category.title' => $this->item->title,
            'category.parent' => $ancestors ? end($ancestors)->title : ''], $ownMenu, $this->absoluteRoute((int) $this->item->id));
        // Социальные теги работают; ItemList добавим вместе с содержимым и ссылками карточек.
        $seo->apply($this->seo, $this->getDocument());
        $pastMenu = $menuCategory <= 1;
        $parentTitle = '';
        foreach (array_merge($ancestors, $this->item->id ? [$this->item] : []) as $ancestor) {
            if ((int) $ancestor->id === $menuCategory) {
                $pastMenu = true;
            } elseif ($pastMenu) {
                $page = $seo->resolve('category', $ancestor, ['category.title' => $ancestor->title,
                    'category.parent' => $parentTitle], new Registry(), $this->absoluteRoute((int) $ancestor->id));
                $app->getPathway()->addItem($page->breadcrumb, $this->categoryRoute((int) $ancestor->id));
            }
            $parentTitle = (string) $ancestor->title;
        }
        $wa = $this->getDocument()->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_dharmashop');
        $wa->useScript('com_dharmashop.listing');
        parent::display($tpl);
    }

    public function categoryRoute(int $id): string
    {
        if ($this->menuId && (int) $this->item->id === $id) {
            $active = Factory::getApplication()->getMenu()->getActive();
            $menuCategory = (int) ($active->query['id'] ?? 0);
            if (($menuCategory <= 1 ? 0 : $menuCategory) === $id) {
                return Route::_('index.php?Itemid=' . $this->menuId, false);
            }
        }

        return Route::_('index.php?option=com_dharmashop&view=category&id=' . $id . ($this->menuId ? '&Itemid=' . $this->menuId : ''), false);
    }

    public function listingRoute(int $after = 0): string
    {
        return Route::_('index.php?option=com_dharmashop&view=category&id=' . (int) $this->item->id
            . ($this->menuId ? '&Itemid=' . $this->menuId : '') . '&sort=' . $this->ordering
            . '&product_view=' . $this->productView . ($after ? '&after=' . $after : ''), false);
    }

    public function renderProduct(object $item): string
    {
        $layout = new FileLayout('components.dharmashop.product-card.' . $this->cardLayout, null, ['component' => 'none', 'client' => 0]);

        return $layout->render(['item' => $item, 'params' => clone $this->params, 'mode' => $this->productView,
            'showIntro' => (int) $this->params->get('product_card_intro', 1) === 1,
            'priceRange' => $this->formatRange($item->price_min, $item->price_max)]);
    }

    public function renderSubcategory(object $item): string
    {
        $stats = $this->subcategoryStats[(int) $item->id] ?? null;
        $layout = new FileLayout('components.dharmashop.category-card.' . $this->subcategoryLayout, null, ['component' => 'none', 'client' => 0]);

        return $layout->render(['item' => $item, 'url' => $this->categoryRoute((int) $item->id),
            'showImage' => (int) $this->params->get('subcategory_show_image', 1) === 1,
            'showIntro' => (int) $this->params->get('category_card_intro', 1) === 1,
            'showCount' => (int) $this->params->get('category_card_count', 0) === 1,
            'showPrices' => (int) $this->params->get('category_card_prices', 0) === 1,
            'productCount' => (int) ($stats->product_count ?? 0),
            'priceRange' => $this->formatRange($stats->price_min ?? null, $stats->price_max ?? null)]);
    }

    private function mergeDisplay(Registry $source, array $keys): void
    {
        foreach ($keys as $key) {
            $value = $source->get($key);
            if ($value !== null && $value !== '' && !($key === 'list_limit' && (int) $value === 0)) {
                $this->params->set($key, $value);
            }
        }
    }

    private function formatRange(?string $min, ?string $max): string
    {
        if ($min === null) {
            return '';
        }
        return $this->money->format($min) . ($max !== null && $min !== $max ? ' — ' . $this->money->format($max) : '');
    }

    private function existingLayout(string $name, string $directory, string $fallback = 'default'): string
    {
        return preg_match('/^[A-Za-z0-9_-]+$/D', $name) && is_file(JPATH_ROOT . '/' . $directory . '/' . $name . '.php') ? $name : $fallback;
    }

    private function absoluteRoute(int $id): string
    {
        $route = $this->categoryRoute($id);
        return preg_match('~^https?://~i', $route) ? $route
            : rtrim(Uri::root(), '/') . '/' . ltrim(substr($route, strlen(Uri::root(true))), '/');
    }
}
