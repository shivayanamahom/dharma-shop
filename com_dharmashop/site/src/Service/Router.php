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

    public function __construct($app, $menu, $categoryFactory, private readonly \Joomla\Database\DatabaseInterface $db)
    {
        $this->registerView((new RouterViewConfiguration('categories'))->setKey('id')->setNestable());
        $this->registerView((new RouterViewConfiguration('category'))->setKey('id')->setNestable());
        // Бренды: список (пункт меню) → страница бренда (вложенная: бренд внутри бренда). Бейджи: список → страница бейджа.
        $brands = new RouterViewConfiguration('brands');
        $this->registerView($brands);
        $this->registerView((new RouterViewConfiguration('brand'))->setKey('id')->setParent($brands)->setNestable());
        $badges = new RouterViewConfiguration('badges');
        $this->registerView($badges);
        $this->registerView((new RouterViewConfiguration('badge'))->setKey('id')->setParent($badges));
        $this->registerView((new RouterViewConfiguration('product'))->setKey('id'));
        parent::__construct($app, $menu);
        $this->attachRule(new MenuRules($this));
        $this->attachRule(new StandardRules($this));
        $this->attachRule(new NomenuRules($this));
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
        return $this->chain((int) $id, null, self::BADGE);
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

            if (!$item) {
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

    private function category(int $id, string $extension = self::CATEGORY): ?object
    {
        $cacheKey = $extension . '/' . $id;

        if (!array_key_exists($cacheKey, $this->categoryCache)) {
            $query = $this->db->createQuery()->select($this->db->quoteName(['id', 'parent_id', 'alias']))
                ->from($this->db->quoteName('#__categories'))->where($this->db->quoteName('id') . ' = :id')
                ->bind(':id', $id, ParameterType::INTEGER)
                ->where($this->db->quoteName('extension') . ' = ' . $this->db->quote($extension));
            $this->categoryCache[$cacheKey] = $this->db->setQuery($query)->loadObject();
        }

        return $this->categoryCache[$cacheKey];
    }
}
