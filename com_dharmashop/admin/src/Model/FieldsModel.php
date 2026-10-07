<?php

/**
 * Dharma Shop — поля каталога.
 * @copyright (C) 2026 Dharma.
 * @license GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Model;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Factory;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

\defined('_JEXEC') or die;

/** Поиск и сортировка групп; количество полей загружается в том же запросе. */
class FieldsModel extends ListModel
{
    protected $option = 'com_dharmashop';

    public function __construct($config = [])
    {
        $config['filter_fields'] ??= ['id', 'a.id', 'title', 'a.title', 'state', 'a.state', 'ordering', 'a.ordering', 'target', 'a.target', 'published'];
        parent::__construct($config);
    }

    protected function populateState($ordering = 'a.ordering', $direction = 'ASC'): void
    {
        parent::populateState($ordering, $direction);

        // Пустое значение (первый вход или «Очистить») возвращает назначение по умолчанию.
        // «Все» имеет отдельное значение *, поэтому остаётся доступным явным выбором.
        $target = (string) $this->state->get('filter.target', 'product');
        $target = in_array($target, ['product', 'category', '*'], true) ? $target : 'product';
        $this->setState('filter.target', $target);

        $app = Factory::getApplication();
        $filters = (array) $app->getUserState($this->context . '.filter', []);
        $filters['target'] = $target;
        $app->setUserState($this->context . '.filter', $filters);
    }

    protected function getStoreId($id = ''): string
    {
        $id .= ':' . $this->getState('filter.search') . ':' . $this->getState('filter.published') . ':' . $this->getState('filter.target');
        return parent::getStoreId($id);
    }

    protected function getListQuery(): QueryInterface
    {
        $db = $this->getDatabase();
        $query = $db->createQuery()
            ->select($db->quoteName(['a.id', 'a.title', 'a.alias', 'a.target', 'a.type', 'a.group_id', 'a.state', 'a.ordering', 'a.checked_out', 'a.checked_out_time']))
            ->select($db->quoteName('u.name', 'editor'))
            ->select($db->quoteName('g.title', 'group_title'))
            ->from($db->quoteName('#__dharmashop_fields', 'a'))
            ->join('LEFT', $db->quoteName('#__dharmashop_fieldgroups', 'g') . ' ON g.id = a.group_id')
            ->join('LEFT', $db->quoteName('#__users', 'u') . ' ON u.id = a.checked_out');

        $published = $this->getState('filter.published');

        $target = (string) $this->getState('filter.target');
        if (in_array($target, ['product', 'category'], true)) {
            $query->where('a.target = :target')->bind(':target', $target);
        }

        if (is_numeric($published)) {
            $published = (int) $published;
            $query->where('a.state = :state')->bind(':state', $published, ParameterType::INTEGER);
        } elseif ($published === '' || $published === null) {
            $query->whereIn($db->quoteName('a.state'), [0, 1]);
        }

        $search = trim((string) $this->getState('filter.search'));

        if (preg_match('/^id:\s*(\d+)$/i', $search, $match)) {
            $id = (int) $match[1];
            $query->where('a.id = :id')->bind(':id', $id, ParameterType::INTEGER);
        } elseif ($search !== '') {
            // Только админский поиск небольшой таблицы определений; витрина его не использует.
            $title = $alias = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
            $query->where('(a.title LIKE :title OR a.alias LIKE :alias)')
                ->bind(':title', $title)->bind(':alias', $alias);
        }

        $ordering = (string) $this->getState('list.ordering', 'a.ordering');
        $ordering = in_array($ordering, ['a.id', 'a.title', 'a.state', 'a.ordering', 'a.target'], true) ? $ordering : 'a.ordering';
        $direction = strtoupper((string) $this->getState('list.direction', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        $query->order($ordering . ' ' . $direction)->order('a.id ' . $direction);

        return $query;
    }
}
