<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Site\Service;

use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Component\Router\Rules\NomenuRules;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * Маршруты витрины без числовых ID: в адресе только алиасы вложенной цепочкой
 * (`/tovary-kategorii/stoly/obedennye-stoly`, `/stoly/obedennye-stoly`). Алиас категории уникален внутри родителя
 * (проверяет `Table\Category`), поэтому разбор однозначен. Адрес строится от пункта меню нужного вида (корень — id 0 или 1).
 * Бренды (`/proizvoditeli/ikea/ikea-hemnes`) и бейджи (`/bejdzhi/hit`) устроены так же; страницы товара появятся позже.
 */
class Router extends RouterView
{
    protected $name = 'dharmashop';
    private const CATEGORY = 'com_dharmashop';
    private const BRAND = 'com_dharmashop.brand';
    private const BADGE = 'com_dharmashop.badge';

    private array $categoryCache = [];
    private array $childCache = [];
    private array $productCache = [];

    /** Prime category routes in one batch before rendering a module or another category collection. */
    public function primeCategories(array $items): void
    {
        foreach ($items as $item) {
            if ((int) ($item->id ?? 0) > 1 && isset($item->parent_id, $item->alias)) {
                $this->categoryCache[self::CATEGORY . '/' . (int) $item->id] = (object) [
                    'id' => (int) $item->id, 'parent_id' => (int) $item->parent_id, 'alias' => (string) $item->alias,
                    // Модули подгружают только опубликованные категории; если признак есть в строке — берём его.
                    'published' => (int) ($item->published ?? 1),
                ];
            }
        }
    }

    public function __construct($app, $menu, $categoryFactory, private readonly \Joomla\Database\DatabaseInterface $db)
    {
        // Как в com_content: список категорий (пункт меню, корень) → категория (вложенная) → товар. Адрес строится от подходящего пункта меню
        // штатными правилами Joomla (точный пункт категории, пункт предка, пункт корня каталога любого из двух типов), разбор работает под любым из них.
        $categories = (new RouterViewConfiguration('categories'))->setKey('id');
        $category = (new RouterViewConfiguration('category'))->setKey('id')->setParent($categories, 'catid')->setNestable();
        $this->registerView($categories);
        $this->registerView($category);
        // Бренды: список (пункт меню) → страница бренда (вложенная: бренд внутри бренда). Бейджи: список → страница бейджа.
        $brands = new RouterViewConfiguration('brands');
        $this->registerView($brands);
        $this->registerView((new RouterViewConfiguration('brand'))->setKey('id')->setParent($brands)->setNestable());
        $badges = new RouterViewConfiguration('badges');
        $this->registerView($badges);
        $this->registerView((new RouterViewConfiguration('badge'))->setKey('id')->setParent($badges));
        // Товар: адрес — адрес категории + алиас товара (`/smartfony/flagmany/apple-iphone-16-pro`); ключ родителя — catid (основная категория).
        $this->registerView((new RouterViewConfiguration('product'))->setKey('id')->setParent($category, 'catid'));
        $this->registerView(new RouterViewConfiguration('cart'));
        $this->registerView(new RouterViewConfiguration('favorites'));
        // Результаты поиска: пункт меню «Поиск» или адрес компонента; запрос — параметр q.
        $this->registerView(new RouterViewConfiguration('search'));
        $this->registerView(new RouterViewConfiguration('checkout'));
        $this->registerView(new RouterViewConfiguration('checkoutcomplete'));
        $this->registerView(new RouterViewConfiguration('order'));
        // Личный кабинет: список заказов пользователя (пункт меню «Мои заказы»).
        $this->registerView(new RouterViewConfiguration('orders'));
        parent::__construct($app, $menu);
        $this->attachRule(new MenuRules($this));
        $this->attachRule(new StandardRules($this));
        $this->attachRule(new NomenuRules($this));
    }

    /** Основная категория товара в запросе (`catid`) нужна для адреса: дополняем её, если ссылку построили только по id товара. */
    public function preprocess($query)
    {
        if (($query['view'] ?? '') === 'product' && !isset($query['catid']) && !empty($query['id'])) {
            $product = $this->product((int) $query['id']);

            if ($product) {
                $query['catid'] = (int) $product->catid;
            }
        }

        return parent::preprocess($query);
    }

    /**
     * У пунктов меню «Бренды» и «Бейджи» в ссылке хранится параметр id корня (0 или 1), но он не ключ вида: без этой правки
     * Joomla дописывает его в адрес («/proizvoditeli?id=0»). Убираем до построения адреса.
     */
    public function build(&$query)
    {
        if (in_array($query['view'] ?? '', ['brands', 'badges'], true) && (int) ($query['id'] ?? 0) <= 1) {
            unset($query['id']);
        }

        return parent::build($query);
    }

    /**
     * Под пунктом меню «список категорий» (например, главная) адрес `/smartfony` разбирается как вложенный список категорий,
     * но у категории всегда должна быть страница с товарами: конкретная категория (id > 1) открывается видом `category`.
     */
    public function parse(&$segments)
    {
        // Старый адрес товара без категории (`/component/dharmashop/product/205`): страница товара сама перенаправит на основной адрес.
        if (count($segments) === 2 && $segments[0] === 'product' && ctype_digit((string) $segments[1])) {
            $product = $this->product((int) $segments[1]);

            if ($product) {
                $segments = [];

                return ['view' => 'product', 'id' => (int) $product->id, 'catid' => (int) $product->catid];
            }
        }

        $vars = parent::parse($segments);

        if (($vars['view'] ?? '') === 'categories' && (int) ($vars['id'] ?? 0) > 1) {
            $vars['view'] = 'category';
        }

        return $vars;
    }

    public function getProductSegment($id, $query): array
    {
        $product = $this->product((int) $id);

        // Алиас неопубликованного товара не раскрываем (301 с адреса `index.php?…&id=N` иначе выдал бы его): сегмент — номер, при разборе будет 404.
        return $product && (int) $product->state === 1 ? [(int) $product->id => (string) $product->alias] : [(int) $id => (string) (int) $id];
    }

    /** Товар по алиасу в категории из запроса (алиас уникален внутри основной категории). */
    public function getProductId($segment, $query): int|false
    {
        $category = max(1, (int) ($query['id'] ?? 0));
        $key = 'alias/' . $category . '/' . $segment;

        if (!array_key_exists($key, $this->productCache)) {
            $db = $this->db;
            $alias = (string) $segment;
            $select = $db->createQuery()->select($db->quoteName('id'))->from($db->quoteName('#__dharmashop_products'))
                ->where($db->quoteName('catid') . ' = :category')->where($db->quoteName('alias') . ' = :alias')->where($db->quoteName('state') . ' = 1')
                ->bind(':category', $category, ParameterType::INTEGER)->bind(':alias', $alias);
            $found = $db->setQuery($select)->loadResult();
            $this->productCache[$key] = $found ? (int) $found : false;
        }

        return $this->productCache[$key];
    }

    public function getCategoriesSegment($id, $query): array
    {
        return $this->chain((int) $id, $this->rootKey($query, 'categories'));
    }

    public function getCategoriesId($segment, $query): int|false
    {
        return $this->child((string) $segment, $query);
    }

    public function getCategorySegment($id, $query): array
    {
        return $this->chain((int) $id, $this->rootKey($query, 'category'));
    }

    public function getCategoryId($segment, $query): int|false
    {
        return $this->child((string) $segment, $query);
    }

    public function getBrandSegment($id, $query): array
    {
        return $this->chain((int) $id, $this->rootKey($query, 'brands'), self::BRAND);
    }

    public function getBrandId($segment, $query): int|false
    {
        return $this->child((string) $segment, $query, self::BRAND);
    }

    public function getBadgeSegment($id, $query): array
    {
        // Вид не вложенный: правило Joomla берёт один элемент, поэтому пустой список (закрытый или чужой номер) недопустим.
        // Номер вместо алиаса при разборе даёт 404, как у закрытого товара.
        return $this->chain((int) $id, null, self::BADGE) ?: [(int) $id => (string) (int) $id];
    }

    public function getBadgeId($segment, $query): int|false
    {
        return $this->child((string) $segment, $query, self::BADGE);
    }

    /** Цепочка алиасов от категории вверх; последний элемент — корень (ключ 0 или 1, значение пустое). */
    private function chain(int $id, ?int $root, string $extension = self::CATEGORY): array
    {
        $segments = [];

        while ($id > 1 && !isset($segments[$id])) {
            $item = $this->category($id, $extension);

            // Закрытая (неопубликованная, в корзине) категория: алиас в адрес не попадает.
            if (!$item || (int) $item->published !== 1) {
                break;
            }

            $segments[$id] = $item->alias;
            $id = (int) $item->parent_id;
        }

        if ($root !== null) {
            $segments[$root] = '';
        }

        return $segments;
    }

    /** Штатное поле выбора категории допускает корень 0 или корень Joomla 1: ключ корня берём из пункта меню этого вида. */
    private function rootKey(array $query, string $view): int
    {
        $menuItem = isset($query['Itemid']) ? $this->menu->getItem($query['Itemid']) : $this->menu->getActive();

        return $menuItem && ($menuItem->query['view'] ?? '') === $view && (int) ($menuItem->query['id'] ?? 0) === 1 ? 1 : 0;
    }

    /** Категория по алиасу внутри родителя из запроса (корень — Joomla 1). */
    private function child(string $alias, array $query, string $extension = self::CATEGORY): int|false
    {
        $parent = max(1, (int) ($query['id'] ?? 0));
        $key = $extension . '/' . $parent . '/' . $alias;

        if (!array_key_exists($key, $this->childCache)) {
            $db = $this->db;
            $select = $db->createQuery()->select($db->quoteName('id'))->from($db->quoteName('#__categories'))
                ->where($db->quoteName('extension') . ' = ' . $db->quote($extension))
                ->where($db->quoteName('parent_id') . ' = :parent')->where($db->quoteName('alias') . ' = :alias')
                ->bind(':parent', $parent, ParameterType::INTEGER)->bind(':alias', $alias);
            $found = $db->setQuery($select)->loadResult();
            $this->childCache[$key] = $found ? (int) $found : false;
        }

        return $this->childCache[$key];
    }

    private function product(int $id): ?object
    {
        $key = 'id/' . $id;

        if (!array_key_exists($key, $this->productCache)) {
            $query = $this->db->createQuery()->select($this->db->quoteName(['id', 'catid', 'alias', 'state']))
                ->from($this->db->quoteName('#__dharmashop_products'))->where($this->db->quoteName('id') . ' = :id')
                ->bind(':id', $id, ParameterType::INTEGER);
            $this->productCache[$key] = $this->db->setQuery($query)->loadObject() ?: null;
        }

        return $this->productCache[$key];
    }

    private function category(int $id, string $extension = self::CATEGORY): ?object
    {
        $cacheKey = $extension . '/' . $id;

        if (!array_key_exists($cacheKey, $this->categoryCache)) {
            $query = $this->db->createQuery()->select($this->db->quoteName(['id', 'parent_id', 'alias', 'published']))
                ->from($this->db->quoteName('#__categories'))->where($this->db->quoteName('id') . ' = :id')
                ->bind(':id', $id, ParameterType::INTEGER)
                ->where($this->db->quoteName('extension') . ' = ' . $this->db->quote($extension));
            $this->categoryCache[$cacheKey] = $this->db->setQuery($query)->loadObject();
        }

        return $this->categoryCache[$cacheKey];
    }
}
