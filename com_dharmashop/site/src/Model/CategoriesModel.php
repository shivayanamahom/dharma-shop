<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Site\Model;

use Dharma\Component\DharmaShop\Site\Support\Taxonomy;
use Dharma\Component\DharmaShop\Site\Support\EntityFields;
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
    /** Потолок счётчика товаров: больше не считаем, чтобы время не зависело от размера каталога. */
    public const COUNT_CAP = 10000;

    protected $option = 'com_dharmashop';
    private ?object $parent = null;
    private array $ancestors = [];
    private ?array $items = null;

    /** Тип записей модели; бренды и бейджи переопределяют. */
    protected function taxonomy(): Taxonomy
    {
        return Taxonomy::category();
    }

    /** Номер категории, заданный кодом (модуль фильтра с фиксированной категорией); null — из адреса страницы. */
    protected ?int $forcedCategory = null;

    /** Задать категорию вместо номера из адреса страницы (до первого обращения к модели). */
    public function forCategory(int $id): static
    {
        $this->forcedCategory = max(0, $id);
        $this->parent = null;

        return $this;
    }

    public function getParent(): object
    {
        if ($this->parent !== null) {
            return $this->parent;
        }
        $id = $this->forcedCategory ?? Factory::getApplication()->getInput()->getInt('id');
        if ($id < 0) {
            throw new \RuntimeException(Text::_('COM_DHARMASHOP_CATEGORY_NOT_FOUND'), 404);
        }
        if ($id <= 1) {
            $taxonomy = $this->taxonomy();
            $root = $taxonomy->rootParam !== '' ? ComponentHelper::getParams('com_dharmashop')->get($taxonomy->rootParam, []) : [];
            $data = json_decode(json_encode($root), true) ?: [];
            // Название корня: из формы корневой страницы, иначе название пункта меню, иначе языковая строка.
            $active = Factory::getApplication()->getMenu()->getActive();
            $menuTitle = $active && $active->component === 'com_dharmashop' ? trim((string) $active->title) : '';
            $default = $menuTitle !== '' ? $menuTitle : Text::_($taxonomy->rootTitle);
            $this->parent = (object) ($data + ['title' => $default, 'description' => '', 'metadesc' => '']);
            if (trim((string) $this->parent->title) === '') {
                $this->parent->title = $default;
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
        if ($this->taxonomy()->area === 'category') { EntityFields::attach($this->getDatabase(), [$this->parent], 'page', 'category'); }
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
            ->where($db->quoteName('extension') . ' = ' . $db->quote($this->taxonomy()->extension))
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
        if ($this->taxonomy()->area === 'category') { EntityFields::attach($db, $this->items, 'card', 'category'); }
        return $this->items;
    }

    /**
     * Итоги для карточек подкатегорий: по каждой категории страницы число товаров (с потолком) и при необходимости цены.
     * Запросы короткие и идут по индексу (время не зависит от размера каталога); их число равно числу карточек страницы.
     *
     * @return array<int, object> catid => (product_count: строка «12» или «10000+», price_min, price_max)
     */
    public function getCardStats(array $items, bool $withPrices): array
    {
        $stats = [];

        foreach (array_unique(array_map(static fn (object $item): int => (int) $item->id, $items)) as $id) {
            $range = $withPrices ? $this->getPriceRange($id) : ['min' => null, 'max' => null];
            $stats[$id] = (object) ['product_count' => $this->getCountLabel($id), 'price_min' => $range['min'], 'price_max' => $range['max']];
        }

        return $stats;
    }

    /** Число товаров для показа: до потолка точно, больше — «10000+». */
    public function getCountLabel(int $categoryId): string
    {
        $count = $this->getPublishedCount($categoryId, self::COUNT_CAP);

        return $count > self::COUNT_CAP ? self::COUNT_CAP . '+' : (string) $count;
    }

    /**
     * Самая низкая и высокая цена опубликованных товаров категории (с вложенными): по копии цены в индексе, без чтения таблицы товаров
     * (`Select tables optimized away`, время не зависит от числа товаров). Корень (0) — без цен.
     *
     * @return  array{min: ?string, max: ?string}
     */
    public function getPriceRange(int $categoryId): array
    {
        if ($categoryId <= 0) {
            return ['min' => null, 'max' => null];
        }

        $db = $this->getDatabase();
        $row = $db->setQuery(
            $db->createQuery()->select('MIN(price_sort) AS pmin, MAX(price_sort) AS pmax')
                ->from($db->quoteName('#__dharmashop_product_categories') . ' FORCE INDEX (idx_l_price)')
                ->where('catid = :c')->where('state = 1')->bind(':c', $categoryId, ParameterType::INTEGER)
        )->loadObject();

        return ['min' => isset($row->pmin) ? (string) $row->pmin : null, 'max' => isset($row->pmax) ? (string) $row->pmax : null];
    }

    /** Число опубликованных товаров категории с потолком: чтение останавливается на $cap + 1 строках. Корень (0) — весь каталог. */
    public function getPublishedCount(int $categoryId, int $cap = 10000): int
    {
        $db = $this->getDatabase();
        $limit = $cap + 1;
        $inner = $categoryId > 0
            ? 'SELECT 1 FROM #__dharmashop_product_categories WHERE catid = ' . $categoryId . ' AND state = 1 LIMIT ' . $limit
            : 'SELECT 1 FROM #__dharmashop_products WHERE state = 1 LIMIT ' . $limit;

        return (int) $db->setQuery('SELECT COUNT(*) FROM (' . $inner . ') AS capped')->loadResult();
    }

    private function loadCategory(int $id): ?object
    {
        $db = $this->getDatabase();
        $query = $db->createQuery()->select($db->quoteName(['id', 'parent_id', 'title', 'alias', 'description', 'params', 'metadata', 'metadesc', 'published', 'access']))
            ->from($db->quoteName('#__categories'))->where($db->quoteName('id') . ' = :id')->bind(':id', $id, ParameterType::INTEGER)
            ->where($db->quoteName('extension') . ' = ' . $db->quote($this->taxonomy()->extension));
        $item = $db->setQuery($query)->loadObject();
        if ($item) {
            $item->params = new Registry($item->params);
            $item->metadata = new Registry($item->metadata);
        }
        return $item;
    }
}
