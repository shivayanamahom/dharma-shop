<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Site\Model;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** Публичная ветка категорий. Доступ проверяется по всей цепочке родителей. */
class CategoriesModel extends BaseDatabaseModel
{
    protected $option = 'com_dharmashop';
    private ?object $parent = null;
    private array $ancestors = [];
    private ?array $items = null;

    public function getParent(): object
    {
        if ($this->parent !== null) {
            return $this->parent;
        }
        $id = Factory::getApplication()->getInput()->getInt('id');
        if ($id < 0) {
            throw new \RuntimeException(Text::_('COM_DHARMASHOP_CATEGORY_NOT_FOUND'), 404);
        }
        if ($id <= 1) {
            $root = ComponentHelper::getParams('com_dharmashop')->get('root_category', []);
            $data = json_decode(json_encode($root), true) ?: [];
            $this->parent = (object) ($data + ['title' => Text::_('COM_DHARMASHOP_CATALOG'), 'description' => '', 'metadesc' => '']);
            if (trim((string) $this->parent->title) === '') {
                $this->parent->title = Text::_('COM_DHARMASHOP_CATALOG');
            }
            $this->parent->id = 0;
            $this->parent->parent_id = 0;
            $this->parent->params = new Registry($data['params'] ?? []);
            $this->parent->metadata = new Registry($data['metadata'] ?? []);
            return $this->parent;
        }
        $chain = [];
        $seen = [];
        while ($id > 1) {
            if (isset($seen[$id])) {
                throw new \RuntimeException(Text::_('COM_DHARMASHOP_CATEGORY_NOT_FOUND'), 404);
            }
            $seen[$id] = true;
            $item = $this->loadCategory($id);
            if (!$item || (int) $item->published !== 1 || !in_array((int) $item->access, $this->getCurrentUser()->getAuthorisedViewLevels(), true)) {
                throw new \RuntimeException(Text::_('COM_DHARMASHOP_CATEGORY_NOT_FOUND'), 404);
            }
            $chain[] = $item;
            $id = (int) $item->parent_id;
        }
        $this->parent = array_shift($chain);
        $this->ancestors = array_reverse($chain);
        return $this->parent;
    }

    public function getAncestors(): array
    {
        $this->getParent();
        return $this->ancestors;
    }

    public function getItems(): array
    {
        if ($this->items !== null) {
            return $this->items;
        }
        $parentId = (int) $this->getParent()->id ?: 1;
        $db = $this->getDatabase();
        $levels = array_map('intval', $this->getCurrentUser()->getAuthorisedViewLevels());
        $query = $db->createQuery()->select($db->quoteName(['id', 'parent_id', 'title', 'alias', 'description', 'params']))
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('extension') . ' = ' . $db->quote('com_dharmashop'))
            ->where($db->quoteName('parent_id') . ' = :parent')->bind(':parent', $parentId, ParameterType::INTEGER)
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('access') . ' IN (' . implode(',', $levels ?: [0]) . ')')
            ->order($db->quoteName('lft') . ' ASC');
        $this->items = [];
        foreach ($db->setQuery($query)->loadObjectList() as $item) {
            $item->params = new Registry($item->params);
            if ((int) $item->params->get('show_in_list', 1) === 1) {
                $this->items[] = $item;
            }
        }
        return $this->items;
    }

    /**
     * Публикуемые товары по индексу, включая унаследованные связи подкатегорий.
     * Один запрос только для карточек текущей страницы, без запросов из layout.
     * @return array<int, object> catid => product_count, price_min/price_max (при запросе цен)
     */
    public function getCardStats(array $items, bool $withPrices): array
    {
        $ids = array_values(array_unique(array_map(static fn (object $item): int => (int) $item->id, $items)));
        if (!$ids) {
            return [];
        }
        $db = $this->getDatabase();
        $query = $db->createQuery()
            ->select($db->quoteName('pc.catid'))
            ->select('COUNT(*) AS ' . $db->quoteName('product_count'))
            ->from($db->quoteName('#__dharmashop_product_categories', 'pc'))
            ->whereIn($db->quoteName('pc.catid'), $ids)
            ->where($db->quoteName('pc.state') . ' = 1')
            ->group($db->quoteName('pc.catid'));
        if ($withPrices) {
            $query->join('INNER', $db->quoteName('#__dharmashop_products', 'p')
                . ' ON ' . $db->quoteName('p.id') . ' = ' . $db->quoteName('pc.product_id'))
                ->where($db->quoteName('p.state') . ' = 1')
                ->select('MIN(' . $db->quoteName('p.price_min') . ') AS ' . $db->quoteName('price_min'))
                ->select('MAX(' . $db->quoteName('p.price_max') . ') AS ' . $db->quoteName('price_max'));
        }

        return $db->setQuery($query)->loadObjectList('catid');
    }

    private function loadCategory(int $id): ?object
    {
        $db = $this->getDatabase();
        $query = $db->createQuery()->select($db->quoteName(['id', 'parent_id', 'title', 'alias', 'description', 'params', 'metadata', 'metadesc', 'published', 'access']))
            ->from($db->quoteName('#__categories'))->where($db->quoteName('id') . ' = :id')->bind(':id', $id, ParameterType::INTEGER)
            ->where($db->quoteName('extension') . ' = ' . $db->quote('com_dharmashop'));
        $item = $db->setQuery($query)->loadObject();
        if ($item) {
            $item->params = new Registry($item->params);
            $item->metadata = new Registry($item->metadata);
        }
        return $item;
    }
}
