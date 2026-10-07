<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\View\Category;

use Dharma\Component\DharmaShop\Administrator\Pricing\MoneyFormatter;
use Dharma\Component\DharmaShop\Site\Model\CategoryModel;
use Dharma\Component\DharmaShop\Site\Placeholder\GlobalValues;
use Dharma\Component\DharmaShop\Site\Placeholder\ProductValues;
use Dharma\Component\DharmaShop\Site\Placeholder\Renderer;
use Dharma\Component\DharmaShop\Site\Support\Grid;
use Dharma\Component\DharmaShop\Site\Support\Taxonomy;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * Общая часть страницы товаров категории и AJAX-фрагмента списка: параметры отображения (настройки → категории → пункт меню),
 * вид и сортировка, порция товаров, карточка товара, адреса. Страница (`HtmlView`) и фрагмент (`RawView`) отдают одинаковый список.
 */
trait ListingTrait
{
    public object $item;
    public array $items = [];
    public Registry $params;
    public string $gridClasses = '';
    public string $cardLayout = 'grid';
    public string $productView = 'grid';
    public string $ordering = 'order';
    public int $menuId = 0;
    public int $after = 0;
    public int $next = 0;
    public string $cardFolder = 'product-card';
    private MoneyFormatter $money;

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
            'product_card_intro', 'category_product_count', 'category_price_range', $taxonomy->cardKey('layout'),
            $taxonomy->cardKey('intro'), $taxonomy->cardKey('count'), $taxonomy->cardKey('prices'), 'list_limit', 'list_ordering',
            'category_default_view', 'subcategory_show_image']);
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
        $this->money = new MoneyFormatter($settings);

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
        $this->ordering = in_array($ordering, CategoryModel::SORTS, true) ? $ordering : 'order';
        $this->after = max(0, $app->getInput()->getInt('after'));

        return $menu;
    }

    /** Читает порцию товаров по ключу (без OFFSET). */
    protected function loadProducts(CategoryModel $model): void
    {
        $limit = max(1, min(200, (int) $this->params->get('list_limit', 24)));
        $products = $model->getProducts($this->ordering, $limit, $this->after);
        $this->items = $products['items'];
        $this->next = $products['next'];
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
     * Адрес порции списка для ссылок без JavaScript: адрес категории и `?after=`. Сортировка и вид в адрес не попадают
     * (их переключает JavaScript без смены адреса); если они пришли в запросе (форма без JavaScript), сохраняются.
     */
    public function listingRoute(int $after = 0): string
    {
        $input = Factory::getApplication()->getInput()->get;
        $query = [];

        if ($input->exists('sort')) {
            $query['sort'] = $this->ordering;
        }

        if ($input->exists('product_view')) {
            $query['product_view'] = $this->productView;
        }

        if ($after) {
            $query['after'] = $after;
        }

        return $this->categoryRoute((int) $this->item->id) . ($query ? '?' . http_build_query($query) : '');
    }

    public function renderProduct(object $item): string
    {
        $layout = new FileLayout('components.dharmashop.' . $this->cardFolder . '.' . $this->cardLayout, null, ['component' => 'none', 'client' => 0]);
        $intro = (string) $item->introtext;

        if ((int) $this->params->get('product_card_intro', 1) === 1) {
            $values = ProductValues::build($item, $this->money) + GlobalValues::build(ComponentHelper::getParams('com_dharmashop'));
            $intro = (new Renderer())->render($intro, $values);
        }

        return $layout->render(['item' => $item, 'params' => clone $this->params, 'mode' => $this->productView,
            'showIntro' => (int) $this->params->get('product_card_intro', 1) === 1,
            'intro' => $intro,
            'priceRange' => $this->formatRange($item->price_min, $item->price_max)]);
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
}
