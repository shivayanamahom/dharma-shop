<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Model;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Factory;
use Dharma\Component\DharmaShop\Administrator\Favorites\FavoritesService;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

\defined('_JEXEC') or die;

/** Read-only marketing overview of current favorite lists. Guest token values are never exposed. */
class FavoritesModel extends ListModel
{
    protected $option = 'com_dharmashop';

    public function __construct($config = [])
    {
        $config['filter_fields'] ??= ['f.id', 'f.user_id', 'f.modified', 'items_count'];
        parent::__construct($config);
    }

    protected function populateState($ordering = 'f.modified', $direction = 'DESC'): void
    {
        parent::populateState($ordering, $direction);
        $this->setState('filter.search', Factory::getApplication()->getInput()->getString('filter_search', ''));
    }

    protected function getListQuery(): QueryInterface
    {
        $db = $this->getDatabase();
        (new FavoritesService($db))->prepareSchema();
        $query = $db->createQuery()->select(['f.id', 'f.user_id', 'f.created', 'f.modified', 'u.name AS user_name', 'u.username', 'u.email'])
            ->select('COUNT(fi.id) AS items_count')->from($db->quoteName('#__dharmashop_favorites', 'f'))
            ->join('LEFT', $db->quoteName('#__users', 'u') . ' ON u.id = f.user_id')
            ->join('LEFT', $db->quoteName('#__dharmashop_favorite_items', 'fi') . ' ON fi.favorites_id = f.id')
            ->group('f.id, f.user_id, f.created, f.modified, u.name, u.username, u.email')
            ->having('COUNT(fi.id) > 0');
        $search = trim((string) $this->getState('filter.search'));
        if ($search !== '') {
            if (ctype_digit($search)) {
                $id = (int) $search;
                $query->where('(f.user_id = :uid OR f.id = :fid)')->bind(':uid', $id, ParameterType::INTEGER)->bind(':fid', $id, ParameterType::INTEGER);
            } else {
                $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
                $query->where('(u.name LIKE :name OR u.username LIKE :username OR u.email LIKE :email)')
                    ->bind(':name', $like)->bind(':username', $like)->bind(':email', $like);
            }
        }
        $order = (string) $this->getState('list.ordering', 'f.modified');
        $order = in_array($order, ['f.id', 'f.user_id', 'f.modified', 'items_count'], true) ? $order : 'f.modified';
        $direction = strtoupper((string) $this->getState('list.direction', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        return $query->order($order . ' ' . $direction)->order('f.id DESC');
    }

    public function getItems()
    {
        $items = parent::getItems();
        if (!$items) { return $items; }
        $db = $this->getDatabase();
        $ids = implode(',', array_map(static fn ($item): int => (int) $item->id, $items));
        $titles = [];
        foreach ($db->setQuery('SELECT fi.favorites_id, p.title FROM #__dharmashop_favorite_items fi '
            . 'JOIN #__dharmashop_products p ON p.id=fi.product_id WHERE fi.favorites_id IN (' . $ids . ') '
            . 'ORDER BY fi.favorites_id, fi.created DESC, fi.id DESC')->loadObjectList() as $row) {
            $titles[(int) $row->favorites_id][] = (string) $row->title;
        }
        foreach ($items as $item) { $item->product_titles = $titles[(int) $item->id] ?? []; }
        return $items;
    }
}
