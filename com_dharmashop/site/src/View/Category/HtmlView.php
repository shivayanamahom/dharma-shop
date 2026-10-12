<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\View\Category;

use Dharma\Component\DharmaShop\Site\Seo\PageMetadata;
use Dharma\Component\DharmaShop\Site\Seo\CollectionMetadata;
use Joomla\CMS\Router\Route;
use Dharma\Component\DharmaShop\Site\Placeholder\CategoryValues;
use Dharma\Component\DharmaShop\Site\Placeholder\GlobalValues;
use Dharma\Component\DharmaShop\Site\Placeholder\Renderer;
use Dharma\Component\DharmaShop\Site\Seo\PageSeo;
use Dharma\Component\DharmaShop\Site\Support\CartAssets;
use Dharma\Component\DharmaShop\Site\Support\Grid;
use Dharma\Component\DharmaShop\Site\Support\MediaUrl;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** Страница товаров категории; список товаров общий с AJAX-фрагментом (`ListingTrait`). */
class HtmlView extends BaseHtmlView
{
    use ListingTrait;

    protected $option = 'com_dharmashop';
    public array $subcategories = [];
    public PageMetadata $seo;
    public bool $showHeading = true;
    public string $image = '';
    public string $subcategoryGridClasses = '';
    public array $sortOptions = [];
    public ?string $productCount = null;
    public string $priceRange = '';
    /** @var array<string, string|callable> значения подстановок страницы (SEO и тексты) */
    private array $textValues = [];
    private string $subcategoryLayout = 'default';
    private array $subcategoryStats = [];

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        $model = $this->getModel();
        $ancestors = $model->getAncestors();
        $menu = $this->prepareListing($model, $ancestors);
        $this->redirectLegacyUrl();
        $this->loadProducts($model);
        // Поиск показывает товары: плитки подкатегорий при запросе не выводятся.
        $this->subcategories = $model->getQueryText() !== '' ? [] : $model->getItems();
        $settings = ComponentHelper::getParams('com_dharmashop');
        $active = $app->getMenu()->getActive();
        $this->showHeading = (int) $menu->get('show_page_heading', 1) === 1;
        $taxonomy = $this->taxonomy();
        $showCategoryImage = $taxonomy->area !== 'category' || (int) $this->params->get('category_page_show_image', 0) === 1;
        $this->image = $showCategoryImage ? MediaUrl::absolute((string) $this->item->params->get('image', '')) : '';
        $this->setLayout($this->existingLayout((string) $this->params->get('category_layout', 'default'), 'components/com_dharmashop/tmpl/' . $taxonomy->pageView));
        $this->subcategoryGridClasses = Grid::classes($this->params, $taxonomy->gridPrefix());
        $this->subcategoryLayout = $this->existingLayout((string) $this->params->get($taxonomy->cardKey('layout'), 'default'), 'layouts/components/dharmashop/' . $taxonomy->cardFolder());
        $this->sortOptions = [
            'order' => Text::_('COM_DHARMASHOP_SORT_ORDER'), 'title' => Text::_('COM_DHARMASHOP_SORT_TITLE'),
            'price_asc' => Text::_('COM_DHARMASHOP_SORT_PRICE_ASC'), 'price_desc' => Text::_('COM_DHARMASHOP_SORT_PRICE_DESC'),
            'new' => Text::_('COM_DHARMASHOP_SORT_NEW'),
        ];

        // При поиске первой в списке — сортировка по релевантности.
        if ($this->searchQuery !== '') {
            $this->sortOptions = ['relevance' => Text::_('COM_DHARMASHOP_SORT_RELEVANCE')] + $this->sortOptions;
        }
        $showCount = (int) $this->params->get('category_product_count', 0) === 1;
        $showPrices = (int) $this->params->get('category_price_range', 0) === 1;
        if ($showCount || $showPrices) {
            $stats = $model->getProductStats($showPrices);
            $this->productCount = $showCount ? (string) $stats->product_count : null;
            $this->priceRange = $showPrices ? $this->formatRange($stats->price_min, $stats->price_max) : '';
        }
        if ((int) $this->params->get($taxonomy->cardKey('count'), 0) || (int) $this->params->get($taxonomy->cardKey('prices'), 0)) {
            $this->subcategoryStats = $model->getCardStats($this->subcategories, (int) $this->params->get($taxonomy->cardKey('prices'), 0) === 1);
        }
        $seo = new PageSeo($settings);
        $menuCategory = max(0, (int) ($active->query['id'] ?? 0));
        $ownMenu = ($menuCategory <= 1 ? 0 : $menuCategory) === (int) $this->item->id ? $menu : new Registry();
        $this->textValues = CategoryValues::build($taxonomy->area, $this->item, $ancestors, $model, $this->money);
        // Сортировка и вид в запросе (форма без JavaScript): страница-дубль, канонический адрес — без параметров, индексировать не нужно.
        $query = $app->getInput()->get;
        $variant = $query->exists('sort') || $query->exists('product_view') || $this->searchQuery !== '' || !$model->getVisitorFilterState()->isEmpty();
        $filtered = !$model->getVisitorFilterState()->isEmpty();
        $canonical = $this->absoluteRoute((int) $this->item->id) . (!$filtered && $this->searchQuery === '' && $this->start ? '?start=' . $this->start : '');
        if ($filtered && (string)$settings->get('filter_canonical','category') === 'self') {
            $parts=['filter'=>$model->getVisitorFilterState()->toArray($model->getFilterDefinition())];
            if ($app->getInput()->getInt('filter_module')) { $parts['filter_module']=$app->getInput()->getInt('filter_module'); }
            if ($this->start) { $parts['start']=$this->start; }
            $canonical .= '?' . preg_replace('/%5B\d+%5D/', '%5B%5D', http_build_query($parts,'','&',PHP_QUERY_RFC3986));
        }
        $robots = $variant ? 'noindex, follow' : null;
        if ($filtered && (int)$settings->get('filter_noindex',1) !== 1 && $this->searchQuery === '') { $robots=null; }
        $this->seo = $seo->resolve($taxonomy->area, $this->item, $this->textValues, $ownMenu, $canonical,
            $robots, $this->pagination ? (int) $this->pagination->pagesCurrent : 1);
        $content = null;
        if ((int) $settings->get('seo_structureddata', 1) === 1) {
            $entries = [];
            foreach ($this->items as $product) {
                $route = Route::_('index.php?option=com_dharmashop&view=product&id=' . (int) $product->id . '&catid=' . (int) $product->catid, false);
                $entries[] = ['name' => (string) ($product->listing_group->title ?? $product->title),
                    'url' => preg_match('~^https?://~i', $route) ? $route : rtrim(Uri::root(), '/') . '/' . ltrim($route, '/')];
            }
            $content = new CollectionMetadata($this->seo->h1 ?: (string) $this->item->title, $entries, 'Product');
        }
        $seo->apply($this->seo, $this->getDocument(), $content);
        $pastMenu = $menuCategory <= 1;
        $chain = [];
        foreach (array_merge($ancestors, $this->item->id ? [$this->item] : []) as $ancestor) {
            if ((int) $ancestor->id === $menuCategory) {
                $pastMenu = true;
            } elseif ($pastMenu) {
                $page = $seo->resolve($taxonomy->area, $ancestor, CategoryValues::build($taxonomy->area, $ancestor, $chain, $model, $this->money),
                    new Registry(), $this->absoluteRoute((int) $ancestor->id), prepareImage: false);
                $app->getPathway()->addItem($page->breadcrumb, $this->categoryRoute((int) $ancestor->id));
            }
            $chain[] = $ancestor;
        }
        $wa = $this->getDocument()->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_dharmashop');
        $wa->useScript('com_dharmashop.listing');

        if ((int) $this->params->get('product_card_sale_countdown', $settings->get('product_card_sale_countdown', 1)) === 1
            && array_filter($this->items, static fn (object $item): bool => !empty($item->sale_end_timestamp))) {
            $wa->useScript('com_dharmashop.sale-countdown');
        }

        // Несколько фото в карточке — карусель Bootstrap (штатный скрипт Joomla, только при необходимости).
        if ($this->cardImagesLimit() !== 1) {
            if ($this->sliderType() === 'swiper') {
                $wa->usePreset('dharmashop.swiper')->useScript('com_dharmashop.card-slider');
            } else {
                $wa->useScript('bootstrap.carousel');
            }
        }

        // Режим каталога (настройки магазина): без корзины — ни виджета количества, ни кнопки, ни их скриптов.
        if (!$this->catalogMode) {
            CartAssets::use($this->getDocument());

            // Кнопки «−»/«+» виджета количества: скрипт только если виджет включён в карточках.
            if ($this->cartMode() === 'qty') {
                $wa->useScript('com_dharmashop.qty');
            }
        }

        // Скрипт — только для выбранного типа пагинации (обычная работает без скриптов).
        $paginationType = (string) $settings->get('pagination_type', 'standard');

        if ($paginationType === 'ajax') {
            $wa->useScript('com_dharmashop.pagination');
        } elseif ($paginationType === 'more' || $paginationType === 'infinite') {
            Text::script('COM_DHARMASHOP_SHOW_MORE');
            $this->getDocument()->addScriptOptions('com_dharmashop.pagination', ['type' => $paginationType]);
            $wa->useScript('com_dharmashop.loadmore');
        }

        parent::display($tpl);
    }

    /**
     * Старые адреса вида `?option=com_dharmashop&view=category&id=13` ведут постоянным перенаправлением на адрес ЧПУ
     * (без параметров сортировки и вида — это состояние страницы, а не её адрес); работает только для запроса с этими параметрами,
     * поэтому петли быть не может.
     */
    private function redirectLegacyUrl(): void
    {
        $app = Factory::getApplication();
        parse_str((string) $app->getInput()->server->getString('QUERY_STRING', ''), $vars);

        if ($app->getInput()->getMethod() !== 'GET') {
            return;
        }

        if (isset($vars['view']) || isset($vars['option'])) {
            $target = $this->absoluteRoute((int) $this->item->id) . ($this->start ? '?start=' . $this->start : '');
            $app->redirect($target, 301);

            return;
        }
    }

    /** Тексты категории (интро, описание) с подстановками {category.title}, {category.price_min} и др. */
    public function text(string $html): string
    {
        return (new Renderer())->renderHtml($html, $this->textValues + GlobalValues::build(ComponentHelper::getParams('com_dharmashop')));
    }

    public function renderSubcategory(object $item): string
    {
        $stats = $this->subcategoryStats[(int) $item->id] ?? null;
        $layout = new FileLayout('components.dharmashop.' . $this->taxonomy()->cardFolder() . '.' . $this->subcategoryLayout, null, ['component' => 'none', 'client' => 0]);

        return $layout->render(['item' => $item, 'url' => $this->categoryRoute((int) $item->id),
            'showImage' => (int) $this->params->get('subcategory_show_image', 1) === 1,
            'showIntro' => (int) $this->params->get($this->taxonomy()->cardKey('intro'), 1) === 1,
            'showCount' => (int) $this->params->get($this->taxonomy()->cardKey('count'), 0) === 1,
            'showPrices' => (int) $this->params->get($this->taxonomy()->cardKey('prices'), 0) === 1,
            'productCount' => (string) ($stats->product_count ?? '0'),
            'priceRange' => $this->formatRange($stats->price_min ?? null, $stats->price_max ?? null)]);
    }

    private function absoluteRoute(int $id): string
    {
        $route = $this->categoryRoute($id);
        return preg_match('~^https?://~i', $route) ? $route
            : rtrim(Uri::root(), '/') . '/' . ltrim(substr($route, strlen(Uri::root(true))), '/');
    }
}
