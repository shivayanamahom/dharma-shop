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
    public object $item;
    public array $items = [];
    public Registry $params;
    public string $gridClasses = '';
    public string $cardLayout = 'grid';
    public string $productView = 'grid';
    public string $ordering = 'order';
    public int $menuId = 0;
    public int $start = 0;
    public ?Pagination $pagination = null;
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
        // Joomla в адресах пишет `start` (штатная навигация); `limitstart` тоже понимается.
        $this->start = max(0, $app->getInput()->getInt('start', $app->getInput()->getInt('limitstart')));

        return $menu;
    }

    /** Читает страницу товаров и готовит штатную постраничную навигацию Joomla. */
    protected function loadProducts(CategoryModel $model): void
    {
        $limit = max(1, min(200, (int) $this->params->get('list_limit', 24)));
        $total = $model->getTotal();
        // Смещение кратно размеру страницы: один адрес на одну страницу.
        $pagination = new Pagination($total, intdiv($this->start, $limit) * $limit, $limit);
        $this->start = $pagination->limitstart;
        $this->items = $total ? $model->getProducts($this->ordering, $limit, $this->start) : [];
        $this->pagination = $total > $limit ? $pagination : null;

        if ($this->pagination) {
            // Фрагмент (format=raw) не должен попадать в ссылки; сортировка и вид сохраняются, только если они пришли в запросе.
            $query = Factory::getApplication()->getInput()->get;
            $pagination->setAdditionalUrlParam('format', null);

            // Ссылки страниц строятся по тому же правилу, что адрес категории (`categoryRoute`): один адрес на страницу.
            if ($this->taxonomy()->area === 'category' && (int) $this->item->id > 1) {
                $pagination->setAdditionalUrlParam('view', $this->hasCatalogMenu() ? 'categories' : 'category');
                $pagination->setAdditionalUrlParam('Itemid', null);
            }

            $pagination->setAdditionalUrlParam('sort', $query->exists('sort') ? $this->ordering : null);
            $pagination->setAdditionalUrlParam('product_view', $query->exists('product_view') ? $this->productView : null);
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

        // Один адрес на категорию, не зависящий от того, откуда пришёл посетитель: категории строятся от пункта «список категорий»
        // (каталог), если он есть, иначе от пункта «Товары категории». Бренды и бейджи — от своих пунктов (Joomla выбирает их сама).
        if ($this->taxonomy()->area === 'category') {
            return Route::_('index.php?option=com_dharmashop&view=' . ($this->hasCatalogMenu() ? 'categories' : 'category') . '&id=' . $id, false);
        }

        return Route::_('index.php?option=com_dharmashop&view=' . $this->taxonomy()->pageView . '&id=' . $id . ($this->menuId ? '&Itemid=' . $this->menuId : ''), false);
    }

    /** Есть ли опубликованный пункт меню «список категорий» корня (id 0 или 1): от него строятся адреса категорий. */
    private function hasCatalogMenu(): bool
    {
        static $found = null;

        if ($found === null) {
            $menu = Factory::getApplication()->getMenu();
            $found = (bool) ($menu->getItems('link', 'index.php?option=com_dharmashop&view=categories&id=0')
                ?: $menu->getItems('link', 'index.php?option=com_dharmashop&view=categories&id=1'));
        }

        return $found;
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
