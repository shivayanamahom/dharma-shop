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
 * Виды `brands`, `badges`, `product` пока без вложенности: их страниц ещё нет.
 */
class Router extends RouterView
{
    protected $name = 'dharmashop';
    private array $categoryCache = [];
    private array $childCache = [];

    public function __construct($app, $menu, $categoryFactory, private readonly \Joomla\Database\DatabaseInterface $db)
    {
        $this->registerView((new RouterViewConfiguration('categories'))->setKey('id')->setNestable());
        $this->registerView((new RouterViewConfiguration('category'))->setKey('id')->setNestable());
        foreach (['product', 'brands', 'badges'] as $view) {
            $config = new RouterViewConfiguration($view);
            if ($view !== 'badges') {
                $config->setKey('id');
            }
            $this->registerView($config);
        }
        parent::__construct($app, $menu);
        $this->attachRule(new MenuRules($this));
        $this->attachRule(new StandardRules($this));
        $this->attachRule(new NomenuRules($this));
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

    /** Цепочка алиасов от категории вверх; последний элемент — корень (ключ 0 или 1, значение пустое). */
    private function chain(int $id, int $root): array
    {
        $segments = [];

        while ($id > 1 && !isset($segments[$id])) {
            $item = $this->category($id);

            if (!$item) {
                break;
            }

            $segments[$id] = $item->alias;
            $id = (int) $item->parent_id;
        }

        $segments[$root] = '';

        return $segments;
    }

    /** Штатное поле выбора категории допускает корень 0 или корень Joomla 1: ключ корня берём из пункта меню этого вида. */
    private function rootKey(array $query, string $view): int
    {
        $menuItem = isset($query['Itemid']) ? $this->menu->getItem($query['Itemid']) : $this->menu->getActive();

        return $menuItem && ($menuItem->query['view'] ?? '') === $view && (int) ($menuItem->query['id'] ?? 0) === 1 ? 1 : 0;
    }

    /** Категория по алиасу внутри родителя из запроса (корень — Joomla 1). */
    private function child(string $alias, array $query): int|false
    {
        $parent = max(1, (int) ($query['id'] ?? 0));
        $key = $parent . '/' . $alias;

        if (!array_key_exists($key, $this->childCache)) {
            $db = $this->db;
            $select = $db->createQuery()->select($db->quoteName('id'))->from($db->quoteName('#__categories'))
                ->where($db->quoteName('extension') . ' = ' . $db->quote('com_dharmashop'))
                ->where($db->quoteName('parent_id') . ' = :parent')->where($db->quoteName('alias') . ' = :alias')
                ->bind(':parent', $parent, ParameterType::INTEGER)->bind(':alias', $alias);
            $found = $db->setQuery($select)->loadResult();
            $this->childCache[$key] = $found ? (int) $found : false;
        }

        return $this->childCache[$key];
    }

    private function category(int $id): ?object
    {
        if (!array_key_exists($id, $this->categoryCache)) {
            $query = $this->db->createQuery()->select($this->db->quoteName(['id', 'parent_id', 'alias']))
                ->from($this->db->quoteName('#__categories'))->where($this->db->quoteName('id') . ' = :id')
                ->bind(':id', $id, ParameterType::INTEGER)
                ->where($this->db->quoteName('extension') . ' = ' . $this->db->quote('com_dharmashop'));
            $this->categoryCache[$id] = $this->db->setQuery($query)->loadObject();
        }

        return $this->categoryCache[$id];
    }
}
