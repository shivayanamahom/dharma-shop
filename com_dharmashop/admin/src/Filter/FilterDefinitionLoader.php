<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Filter;

use Dharma\Component\DharmaShop\Administrator\Fields\TypeRegistry;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * Загрузка определений фильтра категории (C84): 4–5 запросов, результат кэшируется (ключ — категория и уровни доступа посетителя).
 * Поле попадает в фильтр, если оно опубликовано, доступно посетителю, `filterable = 1`, его тип даёт значения-списки или числа
 * (text/editor/файлы/галерея/видео в фильтре не участвуют), оно относится ко всем категориям либо назначено категории, её предку или потомку.
 */
final class FilterDefinitionLoader
{
    /** Типы, которые не могут участвовать в фильтре независимо от флага в БД (C62 и медиа). */
    private const BLOCKED_TYPES = ['text', 'editor', 'files', 'gallery', 'video'];

    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /** @param int[] $levels уровни доступа посетителя */
    public function forCategory(int $catid, array $levels): FilterDefinition
    {
        sort($levels);
        $key = 'definition:' . $catid . ':' . implode(',', $levels);

        return FilterCache::remember($key, fn (): FilterDefinition => $this->load($catid, $levels));
    }

    /** @param int[] $levels */
    private function load(int $catid, array $levels): FilterDefinition
    {
        $levels = array_map('intval', $levels) ?: [0];
        $list = implode(',', $levels);

        // Страница бренда или бейджа: у них своё дерево категорий, поля берутся как для всего каталога.
        if ($catid > 0 && (string) $this->db->setQuery($this->db->createQuery()->select('extension')->from($this->db->quoteName('#__categories'))->where('id = :c')->bind(':c', $catid, ParameterType::INTEGER))->loadResult() !== 'com_dharmashop') {
            $catid = 0;
        }

        return new FilterDefinition($catid, $this->fields($catid, $list), $this->subcategories($catid, $list), $this->directory('com_dharmashop.brand', $list), $this->badges($list));
    }

    /** @return array<int, FilterField> */
    private function fields(int $catid, string $levels): array
    {
        $db = $this->db;
        $providers = TypeRegistry::current()->all();
        $blocked = implode(',', array_map([$db, 'quote'], self::BLOCKED_TYPES));
        $query = $db->createQuery()
            ->select($db->quoteName(['f.id', 'f.alias', 'f.title', 'f.type', 'f.store', 'f.level', 'f.unit', 'f.params']))
            ->from($db->quoteName('#__dharmashop_fields', 'f'))
            ->join('LEFT', $db->quoteName('#__dharmashop_fieldgroups', 'g') . ' ON g.id = f.group_id')
            ->where('f.target = ' . $db->quote('product'))->where('f.state = 1')->where('f.filterable = 1')
            ->where('f.type NOT IN (' . $blocked . ')')
            ->where('f.access IN (' . $levels . ')')
            ->where('(f.group_id = 0 OR (g.state = 1 AND g.target = f.target))')
            ->order('COALESCE(g.ordering, 0), f.group_id, f.ordering, f.id');

        if ($catid > 0) {
            $scope = $this->scope($catid);
            $query->where('(f.all_categories = 1 OR EXISTS (SELECT 1 FROM ' . $db->quoteName('#__dharmashop_category_fields', 'cf')
                . ' WHERE cf.field_id = f.id AND cf.catid IN (' . implode(',', $scope) . ')))');
        }

        $fields = [];
        $options = [];

        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            $provider = $providers[(string) $row->type] ?? null;
            $kind = (string) $row->store;

            if ($provider === null || $provider->store() !== $kind || !in_array($kind, [FilterField::OPTION, FilterField::NUMBER, FilterField::RANGE], true)) {
                continue;
            }

            $fields[(int) $row->id] = $row;
        }

        if (!$fields) {
            return [];
        }

        $ids = implode(',', array_keys($fields));
        $optionQuery = $db->createQuery()->select($db->quoteName(['id', 'field_id', 'alias', 'title', 'color', 'image']))
            ->from($db->quoteName('#__dharmashop_field_options'))->where('field_id IN (' . $ids . ')')->order('ordering, id');

        foreach ($db->setQuery($optionQuery)->loadObjectList() as $option) {
            $options[(int) $option->field_id][(int) $option->id] = new FilterOption((int) $option->id, (string) $option->alias, (string) $option->title, $option->color !== null ? (string) $option->color : null, $option->image !== null ? (string) $option->image : null);
        }

        $result = [];

        foreach ($fields as $id => $row) {
            // Поле-список без значений в фильтре бесполезно.
            if ($row->store === FilterField::OPTION && empty($options[$id])) {
                continue;
            }

            $settings = (array) ((new Registry((string) $row->params))->get('filter') ?? []);
            $result[$id] = new FilterField($id, (string) $row->alias, (string) $row->title, (string) $row->type, (string) $row->store, (int) $row->level, (string) $row->unit, $options[$id] ?? [], $settings);
        }

        return $result;
    }

    /** Категория, её предки и потомки (поле, назначенное ветке, может иметь значения у товаров списка). @return int[] */
    private function scope(int $catid): array
    {
        $db = $this->db;
        $query = $db->createQuery()->select('c.id')->from($db->quoteName('#__categories', 'c'))
            ->join('INNER', $db->quoteName('#__categories', 's') . ' ON s.id = :cid AND s.extension = c.extension AND ((c.lft <= s.lft AND c.rgt >= s.rgt) OR (c.lft >= s.lft AND c.rgt <= s.rgt))')
            ->bind(':cid', $catid, ParameterType::INTEGER);

        return array_map('intval', $db->setQuery($query)->loadColumn()) ?: [$catid];
    }

    /** @return array<int, array{alias: string, title: string}> */
    private function subcategories(int $catid, string $levels): array
    {
        $db = $this->db;
        $query = $db->createQuery()->select($db->quoteName(['c.id', 'c.alias', 'c.title']))->from($db->quoteName('#__categories', 'c'))
            ->where('c.extension = ' . $db->quote('com_dharmashop'))->where('c.published = 1')->where('c.access IN (' . $levels . ')')
            ->where('c.parent_id = :parent')->bind(':parent', $catid, ParameterType::INTEGER)->order('c.lft');
        $rows = [];

        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            $rows[(int) $row->id] = ['alias' => (string) $row->alias, 'title' => (string) $row->title];
        }

        return $rows;
    }

    /** @return array<int, array{alias: string, title: string}> */
    private function directory(string $extension, string $levels): array
    {
        $db = $this->db;
        $query = $db->createQuery()->select($db->quoteName(['c.id', 'c.alias', 'c.title']))->from($db->quoteName('#__categories', 'c'))
            ->where('c.extension = ' . $db->quote($extension))->where('c.published = 1')->where('c.access IN (' . $levels . ')')->where('c.level > 0')->order('c.lft');
        $rows = [];

        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            $rows[(int) $row->id] = ['alias' => (string) $row->alias, 'title' => (string) $row->title];
        }

        return $rows;
    }

    /** @return array<int, array{alias: string, title: string, color: string}> */
    private function badges(string $levels): array
    {
        $rows = [];

        foreach ($this->directory('com_dharmashop.badge', $levels) as $id => $row) {
            $rows[$id] = $row + ['color' => 'primary'];
        }

        return $rows;
    }

    /** Уровни доступа текущего посетителя (для ключа кэша и условий). @return int[] */
    public static function currentLevels(): array
    {
        $identity = Factory::getApplication()->getIdentity();

        return $identity ? array_map('intval', $identity->getAuthorisedViewLevels()) : [1];
    }
}
