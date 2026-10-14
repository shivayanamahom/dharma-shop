<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\View\Category;

use Dharma\Component\DharmaShop\Administrator\Pricing\MoneyFormatter;
use Dharma\Component\DharmaShop\Site\Model\CategoryModel;
use Dharma\Component\DharmaShop\Site\Placeholder\GlobalValues;
use Dharma\Component\DharmaShop\Site\Placeholder\ProductValues;
use Dharma\Component\DharmaShop\Site\Placeholder\Renderer;
use Dharma\Component\DharmaShop\Site\Support\CartSession;
use Dharma\Component\DharmaShop\Site\Support\DisplaySettings;
use Dharma\Component\DharmaShop\Site\Support\Grid;
use Dharma\Component\DharmaShop\Site\Support\MediaUrl;
use Dharma\Component\DharmaShop\Site\Support\ProductCardRenderer;
use Dharma\Component\DharmaShop\Site\Support\Taxonomy;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * Общая часть страницы товаров категории и AJAX-фрагмента списка: параметры отображения (настройки → категории → пункт меню),
 * вид и сортировка, порция товаров, карточка товара, адреса. Страница (`HtmlView`) и фрагмент (`RawView`) отдают одинаковый список.
 */
trait ListingTrait
{
    /** Preserve only validated filter values when native sorting submits the form. */
    public function filterHiddenInputs(): string
    {
        $model = $this->getModel();
        $values = ['filter' => $model->getVisitorFilterState()->toArray($model->getFilterDefinition())];
        $module = Factory::getApplication()->getInput()->getInt('filter_module');
        if ($module > 0 && !$model->getVisitorFilterState()->isEmpty()) { $values['filter_module'] = $module; }
        $html = '';
        $walk = function (array $values, string $prefix = '') use (&$walk, &$html): void {
            foreach ($values as $key => $value) {
                $name = $prefix === '' ? (string) $key : $prefix . '[' . (is_int($key) ? '' : $key) . ']';
                if (is_array($value)) { $walk($value, $name); }
                else { $html .= '<input type="hidden" name="' . $this->escape($name) . '" value="' . $this->escape($value) . '">'; }
            }
        };
        $walk($values);
        return $html;
    }

    public object $item;
    public array $items = [];
    public Registry $params;
    public string $gridClasses = '';
    public string $cardLayout = 'grid';
    public string $productView = 'grid';
    public string $ordering = 'order';
    public string $defaultOrdering = 'order';
    public int $menuId = 0;
    public int $start = 0;
    public bool $catalogMode = false;
    /** @var array<int, array{item: int, qty: float}> товары, уже лежащие в корзине посетителя (режим «кнопка → виджет») */
    public array $cartProducts = [];
    /** HTML шаблона виджета количества: из него cart.js создаёт виджет после добавления */
    public string $stepperTemplate = '';
    /** @var array<string, string> колонки характеристик таблицы товаров: алиас => название (по товарам страницы) */
    public array $fieldColumns = [];
    public array $fieldColumnShowTitle = [];
    public ?Pagination $pagination = null;
    /** Всего товаров в списке (с учётом поиска) */
    public int $total = 0;
    /** Поисковый запрос страницы (`q`) */
    public string $searchQuery = '';
    public string $cardFolder = 'product-card';
    private MoneyFormatter $money;
    private ?\Dharma\Component\DharmaShop\Site\Support\GroupCardRenderer $groupCards = null;
    private ?ProductCardRenderer $productCards = null;

    /** Тип записей страницы; бренды и бейджи переопределяют. */
    protected function taxonomy(): Taxonomy
    {
        return Taxonomy::category();
    }

    /** Параметры, которые категория или пункт меню могут переопределять. */
    private function displayKeys(): array
    {
        $taxonomy = $this->taxonomy();

        return array_merge(Grid::keys('category'), Grid::keys($taxonomy->gridPrefix()), ['category_layout', 'product_card_layout',
            'product_card_intro', 'product_card_qty', 'product_card_cart_mode', 'product_card_images', 'category_product_count', 'category_price_range', $taxonomy->cardKey('layout'),
            $taxonomy->cardKey('intro'), $taxonomy->cardKey('count'), $taxonomy->cardKey('prices'), 'list_limit', 'list_ordering',
            'category_default_view', 'category_listing_mode', 'table_show_fields', 'subcategory_show_image', 'category_page_show_image',
            'product_card_show_discount', 'product_card_discount_display', 'product_card_sale_countdown']);
    }

    /** Собирает параметры, вид и сортировку; возвращает параметры пункта меню этого вида. Товары не читает. */
    protected function prepareListing(CategoryModel $model, array $ancestors): Registry
    {
        $app = Factory::getApplication();
        $settings = ComponentHelper::getParams('com_dharmashop');
        $taxonomy = $this->taxonomy();
        $this->item = $model->getParent();
        $this->params = clone $settings;
        $keys = $this->displayKeys();
        $this->cardFolder = 'product-card';

        // Бренды и бейджи: своя страница-макет (копия страницы товаров категории, готовая к правке) и переключатель карточек:
        // включён (по умолчанию) — карточки товаров как в категориях, выключен — собственные карточки `{area}-product-card`.
        // Остальное (колонки, вид, интро, лимит) наследуется от категорий и переопределяется в записи.
        if ($taxonomy->area !== 'category') {
            $this->params->set('category_layout', (string) $settings->get($taxonomy->area . '_layout', ''));

            if ((int) $settings->get($taxonomy->area . '_use_category_cards', 1) === 0) {
                $this->cardFolder = $taxonomy->area . '-product-card';
                $this->params->set('product_card_layout', (string) $settings->get($taxonomy->area . '_product_card_layout', ''));
            }
        }

        foreach (array_merge($ancestors, [$this->item]) as $ancestor) {
            $this->mergeDisplay($ancestor->params, $keys);
        }

        $active = $app->getMenu()->getActive();
        $ownView = $active && $active->component === 'com_dharmashop' && ($active->query['view'] ?? '') === $taxonomy->pageView;
        $menu = $ownView ? $active->getParams() : new Registry();
        $this->menuId = $ownView ? (int) $active->id : 0;
        $this->mergeDisplay($menu, $keys);
        $model->setListingMode((string) $this->params->get('category_listing_mode', 'products'));
        $this->money = new MoneyFormatter($settings);
        $this->catalogMode = (int) $settings->get('catalog_mode', 0) === 1;

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
            'layouts/components/dharmashop/' . $this->cardFolder, $this->productView);
        $this->gridClasses = Grid::classes($this->params, 'category');
        $ordering = $app->getInput()->getCmd('sort', (string) $this->params->get('list_ordering', 'order'));
        $this->searchQuery = $model->getQueryText();
        $this->defaultOrdering = $this->searchQuery !== '' ? 'relevance' : (string) $this->params->get('list_ordering', 'order');
        $this->redirectFirstPage();
        $this->redirectToCanonicalFilter($model);
        $this->redirectToMatchingLanding($model);
        // С поисковым запросом по умолчанию — по релевантности.
        $this->ordering = $this->searchQuery !== '' && !$app->getInput()->exists('sort') ? 'relevance'
            : (in_array($ordering, CategoryModel::SORTS, true) ? $ordering : 'order');
        // Joomla в адресах пишет `start` (штатная навигация); `limitstart` тоже понимается.
        $this->start = max(0, $app->getInput()->getInt('start', $app->getInput()->getInt('limitstart')));

        return $menu;
    }

    /** Ссылка на первый лист с `start=0` — дубль адреса без параметра: 301 на чистый адрес (остальные параметры сохраняются). */
    protected function redirectFirstPage(): void
    {
        $app = Factory::getApplication();
        $input = $app->getInput();

        if ($input->getMethod() !== 'GET' || $input->getCmd('format') === 'raw') {
            return;
        }

        $zero = [];

        foreach (['start', 'limitstart'] as $name) {
            if ($input->get->exists($name) && $input->get->getInt($name) === 0 && trim((string) $input->get->getString($name)) !== '') {
                $zero[] = $name;
            }
        }

        if (!$zero) {
            return;
        }

        $uri = clone \Joomla\CMS\Uri\Uri::getInstance();

        foreach ($zero as $name) {
            $uri->delVar($name);
        }

        $app->redirect($uri->toString(), 301);
    }

    /**
     * Адрес с условиями фильтра приводится к каноническому виду (алиасы, без пустых и неизвестных параметров, стабильный порядок):
     * форма без JavaScript отправляет и пустые поля, а один выбор должен иметь один адрес (кэш, поисковики, ссылки).
     */
    protected function redirectToCanonicalFilter(CategoryModel $model): void
    {
        $app = Factory::getApplication();
        $input = $app->getInput();

        if ($input->getMethod() !== 'GET' || $input->getCmd('format') === 'raw') {
            return;
        }

        $requested = $input->get->get('filter', [], 'array');
        $managed = ['f', 'price', 'brand', 'badge', 'cat', 'stock', 'sale'];

        if (!is_array($requested) || (!array_intersect($managed, array_keys($requested)) && !$input->get->exists('filter_module'))) {
            return;
        }

        $canonical = $model->getVisitorFilterState()->toArray($model->getFilterDefinition());
        $keep = $this->searchQuery !== '' ? ['search' => $this->searchQuery] : [];
        $target = $canonical + $keep;

        $orphanModule = !$canonical && $input->get->exists('filter_module');
        if (!$orphanModule && $this->normalizeQuery($requested) === $this->normalizeQuery($target)) {
            return;
        }

        $uri = clone \Joomla\CMS\Uri\Uri::getInstance();
        $uri->delVar('filter');
        if (!$canonical) { $uri->delVar('filter_module'); }
        if ($this->normalizeQuery($requested) !== $this->normalizeQuery($target)) { $uri->delVar('start'); }
        $rest = $uri->getQuery();
        // Списки значений — `filter[f][поле][]=…` (как их отправляет форма), а не `[0]`, `[1]`.
        $filterQuery = $target ? (string) preg_replace('/%5B\d+%5D/', '%5B%5D', http_build_query(['filter' => $target], '', '&', PHP_QUERY_RFC3986)) : '';
        $query = trim($rest . ($rest !== '' && $filterQuery !== '' ? '&' : '') . $filterQuery, '&');

        // Строка собирается вручную: `Uri::toString()` пересобирает запрос и снова расставляет индексы.
        $app->redirect($uri->toString(['path']) . ($query !== '' ? '?' . $query : ''), 301);
    }

    /**
     * Выбор в фильтре категории совпадает с условиями опубликованной посадочной страницы (та же категория и те же условия) —
     * постоянный редирект на страницу: один адрес на одну подборку, сигналы ранжирования не делятся. Только чистый адрес:
     * сортировка, вид, поиск и навигация (варианты страницы) остаются на категории и закрыты от индексации. Если компонент
     * посадочных страниц не установлен, ничего не происходит.
     */
    protected function redirectToMatchingLanding(CategoryModel $model): void
    {
        $app = Factory::getApplication();
        $input = $app->getInput();

        if ($input->getMethod() !== 'GET' || $input->getCmd('format') === 'raw' || $this->searchQuery !== '' || $input->get->exists('sort')
            || $input->get->exists('product_view') || $input->getInt('start') > 0 || $input->getInt('limitstart') > 0) {
            return;
        }

        if (($url = $this->matchingLandingUrl($model)) !== null) {
            $app->redirect($url, 301);
        }
    }

    private ?string $landingUrl = null;
    private bool $landingLooked = false;

    /** Адрес посадочной страницы с теми же категорией и условиями, что выбрал посетитель, или null. Работает только для категорий. */
    protected function matchingLandingUrl(CategoryModel $model): ?string
    {
        $lookup = '\\Dharma\\Component\\DharmaShopLanding\\Site\\Service\\LandingLookup';

        if ($this->landingLooked) {
            return $this->landingUrl;
        }

        $this->landingLooked = true;

        if ($this->taxonomy()->area !== 'category' || !class_exists($lookup)) {
            return null;
        }

        $state = $model->getVisitorFilterState();

        if ($state->isEmpty()) {
            return null;
        }

        return $this->landingUrl = $lookup::urlFor(Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class), (int) $this->item->id, $state->toArray($model->getFilterDefinition()), $this->getCurrentUser()->getAuthorisedViewLevels());
    }

    /** @param array<mixed> $filter */
    private function normalizeQuery(array $filter): string
    {
        ksort($filter);

        return http_build_query($filter, '', '&', PHP_QUERY_RFC3986);
    }

    /** Читает страницу товаров и готовит штатную постраничную навигацию Joomla. */
    protected function loadProducts(CategoryModel $model): void
    {
        if ($model->groupsEnabled()) { \Dharma\Component\DharmaShop\Site\Support\GroupCardRenderer::assets(); }
        $limit = max(1, min(200, (int) $this->params->get('list_limit', 24)));
        $total = $model->getTotal();
        $this->total = $total;
        // Смещение за последней страницей — нет такой страницы (раньше отдавался 200 с последним листом: дубль адреса).
        if ($this->start > 0 && $this->start >= $total) {
            throw new \RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404);
        }
        // Смещение кратно размеру страницы: один адрес на одну страницу.
        $pagination = new Pagination($total, intdiv($this->start, $limit) * $limit, $limit);
        $this->start = $pagination->limitstart;
        $this->items = $total ? $model->getProducts($this->ordering, $limit, $this->start) : [];
        $model->attachCardData($this->items, $this->cardImagesLimit());
        $this->loadCartState();

        // Таблица: колонки характеристик — объединение полей, выводимых в карточках товаров этой страницы.
        $this->fieldColumns = [];
        $this->fieldColumnShowTitle = [];

        if ($this->productView === 'table' && (int) $this->params->get('table_show_fields', 0) === 1) {
            foreach ($this->items as $product) {
                if (\Dharma\Component\DharmaShop\Site\Support\GroupCardRenderer::isGroup($product)) { continue; }
                foreach ($product->fields ?? [] as $alias => $field) {
                    if (!empty($field->show) && !empty($field->values)) {
                        $this->fieldColumns[(string) $alias] ??= (string) $field->title;
                        $this->fieldColumnShowTitle[(string) $alias] ??= $field->show_title ?? true;
                    }
                }
            }
        }
        $this->pagination = $total > $limit ? $pagination : null;

        if ($this->pagination) {
            // Фрагмент (format=raw) не должен попадать в ссылки; сортировка и вид сохраняются, только если они пришли в запросе.
            $query = Factory::getApplication()->getInput()->get;
            $pagination->setAdditionalUrlParam('format', null);

            $pagination->setAdditionalUrlParam('sort', $query->exists('sort') && $this->ordering !== $this->defaultOrdering ? $this->ordering : null);
            $pagination->setAdditionalUrlParam('product_view', $query->exists('product_view') ? $this->productView : null);
            // Условия фильтра и поиск остаются в ссылках страниц (канонический вид: алиасы).
            $filterParams = $model->getVisitorFilterState()->toArray($model->getFilterDefinition()) + ($this->searchQuery !== '' ? ['search' => $this->searchQuery] : []);
            $pagination->setAdditionalUrlParam('filter', $filterParams ?: null);
            $pagination->setAdditionalUrlParam('filter_module', $filterParams ? ($query->getInt('filter_module') ?: null) : null);
        }
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

        return Route::_('index.php?option=com_dharmashop&view=' . $this->taxonomy()->pageView . '&id=' . $id . ($this->menuId ? '&Itemid=' . $this->menuId : ''), false);
    }

    /**
     * Слайдер фото: Swiper (набор `dharmashop.swiper` зарегистрирован включённым плагином и выбран в настройках) или карусель
     * Bootstrap. Один выбор на карточки и страницу товара.
     */
    protected function sliderType(): string
    {
        $registry = Factory::getApplication()->getDocument()->getWebAssetManager()->getRegistry();

        return ComponentHelper::getParams('com_dharmashop')->get('product_gallery_type', 'swiper') !== 'bootstrap'
            && $registry->exists('preset', 'dharmashop.swiper') ? 'swiper' : 'bootstrap';
    }

    /** Сколько фото выводить в карточке: 1 — только первое (по умолчанию), 2–5 — столько, 0 — все. */
    protected function cardImagesLimit(): int
    {
        $value = (string) $this->params->get('product_card_images', '1');

        return in_array($value, ['0', '1', '2', '3', '4', '5'], true) ? (int) $value : 1;
    }

    /** Фото карточки (абсолютные адреса): галерея, если выводится больше одного фото, иначе обложка. */
    private function cardImages(object $item): array
    {
        $images = [];

        foreach ($item->images ?? [] as $image) {
            if ($url = MediaUrl::absolute($image->path)) {
                $images[] = (object) ['url' => $url, 'alt' => $image->alt !== '' ? $image->alt : (string) $item->title];
            }
        }

        if (!$images && ($url = MediaUrl::absolute((string) ($item->cover ?? '')))) {
            $images[] = (object) ['url' => $url, 'alt' => (string) ($item->cover_alt ?: $item->title)];
        }

        return $images;
    }

    /**
     * Режим покупки в карточке: button (только кнопка), qty (поле количества и кнопка), stepper (кнопка → виджет после добавления).
     * Настройки → категория → пункт меню; если новая настройка не задана — прежний переключатель «виджет количества» (qty), иначе stepper.
     */
    public function cartMode(): string
    {
        if ($this->catalogMode) {
            return 'button';
        }

        $mode = (string) $this->params->get('product_card_cart_mode', '');

        return in_array($mode, ['button', 'qty', 'stepper'], true) ? $mode : ((int) $this->params->get('product_card_qty', 0) === 1 ? 'qty' : 'stepper');
    }

    /**
     * Режим «кнопка → виджет»: какие товары уже в корзине. Один запрос по корзине посетителя (нет cookie корзины — запроса нет);
     * страница с корзиной не кешируется (состояние персональное). Шаблон виджета выводится один раз на страницу.
     */
    private function loadCartState(): void
    {
        $this->cartProducts = [];
        $this->stepperTemplate = '';

        if ($this->cartMode() !== 'stepper') {
            return;
        }

        $app = Factory::getApplication();
        $session = new CartSession($app->bootComponent('com_dharmashop')->getServices()->cart(), $app);
        $cart = $session->current();

        if ($cart !== null) {
            $this->cartProducts = $session->service()->productQuantities($cart);
            $app->allowCache(false);
        }

        ProductCardRenderer::$stepperTemplateEmitted = true;
        $this->stepperTemplate = (new FileLayout('components.dharmashop.product-card-parts.stepper', null, ['component' => 'none', 'client' => 0]))->render(['qty' => 1, 'step' => 1, 'min' => 1]);
    }

    /** Адрес страницы бейджа (кэшируется: на странице бейджей мало, карточек много). */
    private function badgeRoute(int $id): string
    {
        static $routes = [];

        return $routes[$id] ??= Route::_('index.php?option=com_dharmashop&view=badge&id=' . $id, false);
    }

    public function renderProduct(object $item): string
    {
        if (\Dharma\Component\DharmaShop\Site\Support\GroupCardRenderer::isGroup($item)) {
            $this->groupCards ??= new \Dharma\Component\DharmaShop\Site\Support\GroupCardRenderer();
            return $this->groupCards->render($item, $this->params, $this->productView, $this->fieldColumns, $this->cartProducts);
        }
        // Единая точка сборки карточки: в default.php позже подключаются отдельные части (слайдер, цена, действия и т. п.).
        $layout = new FileLayout('components.dharmashop.product-card.default', null, ['component' => 'none', 'client' => 0]);
        $intro = (string) $item->introtext;
        $settings = ComponentHelper::getParams('com_dharmashop');

        if ((int) $this->params->get('product_card_intro', 1) === 1) {
            $values = ProductValues::build($item, $this->money) + GlobalValues::build($settings);
            $intro = (new Renderer())->render($intro, $values);
        }

        $displayData = ['item' => $item, 'params' => clone $this->params, 'mode' => $this->productView,
            'cardFolder' => $this->cardFolder, 'cardLayout' => $this->cardLayout,
            'fields' => $item->fields ?? [],
            'fieldColumns' => $this->fieldColumns,
            'productUrl' => Route::_('index.php?option=com_dharmashop&view=product&id=' . (int) $item->id . '&catid=' . (int) $item->catid, false),
            'showIntro' => (int) $this->params->get('product_card_intro', 1) === 1,
            'intro' => $intro,
            'priceRange' => $this->formatRange($item->price_min, $item->price_max, !empty($item->price_free)),
            'oldPrice' => $item->base_price !== null ? $this->money->format($item->base_price) : '',
            'discountAmount' => $item->discount_amount !== null ? $this->money->format($item->discount_amount) : '',
            'discountPercent' => (float) $item->discount_percent,
            'showDiscount' => (int) $this->params->get('product_card_show_discount', $settings->get('product_card_show_discount', 1)) === 1,
            'discountDisplay' => $this->params->get('product_card_discount_display', $settings->get('product_card_discount_display', 'percent')) === 'amount' ? 'amount' : 'percent',
            'saleEndTimestamp' => (int) ($item->sale_end_timestamp ?? 0),
            'showSaleCountdown' => (int) $this->params->get('product_card_sale_countdown', $settings->get('product_card_sale_countdown', 1)) === 1,
            'saleBadgeId' => (int) $settings->get('sale_badge_id', 0),
            'badges' => array_map(fn (object $badge): object => (object) ((array) $badge + ['url' => $this->badgeRoute($badge->id)]), $item->badges),
            'categoryTitle' => (string) ($item->category_title ?? ''),
            'categoryUrl' => $this->categoryRoute((int) $item->catid),
            'images' => $this->cardImages($item),
            'sliderType' => $this->sliderType(),
            'catalogMode' => $this->catalogMode,
            'cartText' => trim((string) ComponentHelper::getParams('com_dharmashop')->get('cart_button_text', '')),
            'cartMode' => $this->cartMode(),
            'inCartQty' => (float) ($this->cartProducts[(int) $item->id]['qty'] ?? 0),
            'inCartItem' => (int) ($this->cartProducts[(int) $item->id]['item'] ?? 0)];

        $taxonomy = $this->taxonomy();
        $context = $taxonomy->pageView === 'search' ? 'search' : 'product.' . $taxonomy->area;
        $favoriteData = ($this->productCards ??= new ProductCardRenderer($this->params, $this->cartProducts))
            ->data($item, $this->productView, $context, 'listing-' . $taxonomy->pageView, true);
        foreach (['favoritesEnabled', 'favoriteIcon', 'favorite', 'favoritesTokenName', 'favoriteMediaActions', 'favoriteActions'] as $key) {
            $displayData[$key] = $favoriteData[$key] ?? null;
        }
        $displayData['imageContext'] = $context . '.' . $this->productView;

        return $layout->render($displayData);
    }

    private function mergeDisplay(Registry $source, array $keys): void
    {
        $keys = array_values(array_filter($keys, static fn (string $key): bool => $key !== 'list_limit'));
        DisplaySettings::merge($this->params, $source, $keys);

        $limit = $source->get('list_limit');
        if ($limit !== null && $limit !== '' && (int) $limit !== 0) {
            $this->params->set('list_limit', $limit);
        }
    }

    private function formatRange(?string $min, ?string $max, bool $free = false): string
    {
        return $this->money->range($min, $max, $free);
    }

    private function existingLayout(string $name, string $directory, string $fallback = 'default'): string
    {
        return preg_match('/^[A-Za-z0-9_-]+$/D', $name) && is_file(JPATH_ROOT . '/' . $directory . '/' . $name . '.php') ? $name : $fallback;
    }
}
