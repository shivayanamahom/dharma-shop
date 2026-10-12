<?php

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

\defined('_JEXEC') or die;

/** Native Joomla list of fields currently available on the saved edit form. */
final class QuickfieldsModel extends ListModel
{
    protected $option = 'com_dharmashop';

    private array $fieldIds = [];
    private string $target = 'product';

    public function __construct($config = [])
    {
        $config['filter_fields'] ??= ['id', 'a.id', 'title', 'a.title', 'ordering', 'a.ordering', 'target', 'a.target'];
        parent::__construct($config);
    }

    protected function populateState($ordering = 'a.ordering', $direction = 'ASC'): void
    {
        $input = Factory::getApplication()->getInput();
        $this->target = in_array($input->getCmd('target', 'product'), ['product', 'category'], true)
            ? $input->getCmd('target', 'product') : 'product';
        $this->fieldIds = array_values(array_unique(array_filter(array_map('intval', explode(',', $input->getString('field_ids', ''))))));
        $this->context .= '.picker.' . $this->target . '.' . md5(implode(',', $this->fieldIds));
        $this->filterFormName = 'filter_quickfields';
        parent::populateState($ordering, $direction);
        $this->setState('filter.target', $this->target);
    }

    protected function getStoreId($id = ''): string
    {
        return parent::getStoreId($id . ':' . $this->getState('filter.search') . ':' . $this->getState('filter.target'));
    }

    protected function getListQuery(): QueryInterface
    {
        $db = $this->getDatabase();
        $query = $db->createQuery()
            ->select($db->quoteName(['a.id', 'a.title', 'a.alias', 'a.target', 'a.type', 'a.group_id', 'a.ordering']))
            ->select($db->quoteName('g.title', 'group_title'))
            ->from($db->quoteName('#__dharmashop_fields', 'a'))
            ->join('LEFT', $db->quoteName('#__dharmashop_fieldgroups', 'g') . ' ON g.id = a.group_id')
            ->where('a.state = 1')
            ->where('a.level = 1')
            ->where('a.target = :target')->bind(':target', $this->target)
            ->whereIn($db->quoteName('a.id'), $this->fieldIds ?: [0]);

        $levels = array_values(array_filter(array_map('intval', Factory::getApplication()->getIdentity()->getAuthorisedViewLevels())));
        $query->whereIn($db->quoteName('a.access'), $levels ?: [0]);

        $search = trim((string) $this->getState('filter.search'));
        if (preg_match('/^id:\s*(\d+)$/i', $search, $match)) {
            $query->where('a.id = :searchId')->bind(':searchId', (int) $match[1], ParameterType::INTEGER);
        } elseif ($search !== '') {
            $pattern = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
            $query->where('(a.title LIKE :title OR a.alias LIKE :alias)')
                ->bind(':title', $pattern)->bind(':alias', $pattern);
        }

        $ordering = (string) $this->getState('list.ordering', 'a.ordering');
        $ordering = in_array($ordering, ['a.id', 'a.title', 'a.ordering'], true) ? $ordering : 'a.ordering';
        $direction = strtoupper((string) $this->getState('list.direction', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        return $query->order($ordering . ' ' . $direction)->order('a.id ' . $direction);
    }
}
