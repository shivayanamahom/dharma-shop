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

/** Первый публичный маршрут: дерево списка категорий с устойчивыми ID в сегментах. */
class Router extends RouterView
{
    protected $name = 'dharmashop';
    private array $categoryCache = [];

    public function __construct($app, $menu, $categoryFactory, private readonly \Joomla\Database\DatabaseInterface $db)
    {
        $this->registerView((new RouterViewConfiguration('categories'))->setKey('id')->setNestable());
        foreach (['category', 'product', 'brands', 'badges'] as $view) {
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
        $segments = [];
        $id = (int) $id;
        while ($id > 1 && !isset($segments[$id])) {
            $item = $this->category($id);
            if (!$item) {
                break;
            }
            $segments[$id] = $id . ':' . $item->alias;
            $id = (int) $item->parent_id;
        }
        // Штатное поле выбора категории допускает корень 0 или Joomla root=1.
        $menuItem = isset($query['Itemid']) ? $this->menu->getItem($query['Itemid']) : $this->menu->getActive();
        $root = $menuItem && ($menuItem->query['view'] ?? '') === 'categories' && (int) ($menuItem->query['id'] ?? 0) === 1 ? 1 : 0;
        $segments[$root] = '';
        return $segments;
    }

    public function getCategoriesId($segment, $query): int|false
    {
        $id = (int) $segment;
        $item = $this->category($id);
        $parent = max(1, (int) ($query['id'] ?? 0));
        if (!$item || (int) $item->parent_id !== $parent || $id . '-' . $item->alias !== $segment) {
            return false;
        }
        return $id;
    }

    public function getCategorySegment($id, $query): array
    {
        $id = (int) $id;
        if ($id <= 1) {
            return [$id => ''];
        }
        $item = $this->category($id);
        return $item ? [$id => $id . ':' . $item->alias] : [];
    }

    public function getCategoryId($segment, $query): int|false
    {
        $id = (int) $segment;
        $item = $this->category($id);
        return $item && $id . '-' . $item->alias === $segment ? $id : false;
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
