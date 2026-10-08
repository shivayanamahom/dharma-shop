<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

\defined('_JEXEC') or die;

/**
 * Корзины магазина (раздел «Корзины» в админке): гости и пользователи, состав и сумма по текущим ценам, срок жизни. Только чтение
 * (удаление выбранных и очистка просроченных — действия контроллера). Агрегаты считаются одним запросом на страницу корзин.
 */
class CartsModel extends ListModel
{
    protected $option = 'com_dharmashop';

    public function __construct($config = [])
    {
        $config['filter_fields'] ??= ['c.id', 'c.created', 'c.modified', 'c.expires'];
        parent::__construct($config);
    }

    protected function populateState($ordering = 'c.modified', $direction = 'DESC'): void
    {
        parent::populateState($ordering, $direction);
    }

    protected function getStoreId($id = ''): string
    {
        return parent::getStoreId($id . ':' . $this->getState('filter.search') . ':' . $this->getState('filter.kind') . ':' . $this->getState('filter.state') . ':' . $this->getState('filter.items'));
    }

    protected function getListQuery(): QueryInterface
    {
        $db = $this->getDatabase();
        $now = Factory::getDate()->toSql();
        $query = $db->createQuery()
            ->select(['c.id', 'c.token', 'c.user_id', 'c.customer_id', 'c.price_list_id', 'c.created', 'c.modified', 'c.expires',
                'u.name AS user_name', 'u.username AS user_username', 'u.email AS user_email', 'cu.name AS customer_name', 'cu.email AS customer_email'])
            ->from($db->quoteName('#__dharmashop_carts', 'c'))
            ->join('LEFT', $db->quoteName('#__users', 'u') . ' ON u.id = c.user_id')
            ->join('LEFT', $db->quoteName('#__dharmashop_customers', 'cu') . ' ON cu.id = c.customer_id');
        $kind = (string) $this->getState('filter.kind');

        if ($kind === 'guest') {
            $query->where('c.user_id IS NULL');
        } elseif ($kind === 'user') {
            $query->where('c.user_id IS NOT NULL');
        }

        $state = (string) $this->getState('filter.state');

        if ($state === 'active') {
            $query->where('c.expires > :now1')->bind(':now1', $now);
        } elseif ($state === 'expired') {
            $query->where('c.expires <= :now2')->bind(':now2', $now);
        }

        $items = (string) $this->getState('filter.items');

        if ($items === 'filled') {
            $query->where('EXISTS (SELECT 1 FROM ' . $db->quoteName('#__dharmashop_cart_items', 'x') . ' WHERE x.cart_id = c.id)');
        } elseif ($items === 'empty') {
            $query->where('NOT EXISTS (SELECT 1 FROM ' . $db->quoteName('#__dharmashop_cart_items', 'x') . ' WHERE x.cart_id = c.id)');
        }

        $search = trim((string) $this->getState('filter.search'));

        if ($search !== '') {
            if (ctype_digit($search)) {
                $id = (int) $search;
                $query->where('(c.id = :sid OR c.user_id = :suser)')->bind(':sid', $id, ParameterType::INTEGER)->bind(':suser', $id, ParameterType::INTEGER);
            } else {
                $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
                $prefix = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
                $query->where('(u.name LIKE :s1 OR u.username LIKE :s2 OR u.email LIKE :s3 OR cu.name LIKE :s4 OR cu.email LIKE :s5 OR c.token LIKE :s6)')
                    ->bind(':s1', $like)->bind(':s2', $like)->bind(':s3', $like)->bind(':s4', $like)->bind(':s5', $like)->bind(':s6', $prefix);
            }
        }

        $order = (string) $this->getState('list.ordering', 'c.modified');
        $order = in_array($order, ['c.id', 'c.created', 'c.modified', 'c.expires'], true) ? $order : 'c.modified';
        $direction = strtoupper((string) $this->getState('list.direction', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        return $query->order($order . ' ' . $direction)->order('c.id DESC');
    }

    /** Корзины страницы со сводкой и составом: lines, qty, total (по текущим ценам), items (первые позиции). */
    public function getItems()
    {
        $items = parent::getItems();

        if (!$items) {
            return $items;
        }

        $db = $this->getDatabase();
        $ids = implode(',', array_map(static fn (object $item): int => (int) $item->id, $items));
        $summary = [];

        foreach ($db->setQuery(
            'SELECT ci.cart_id, COUNT(*) AS lines_count, SUM(ci.qty) AS qty_sum, SUM(ci.qty * COALESCE(vp.price_final, 0)) AS total_sum, SUM(vp.price_final IS NULL) AS no_price '
            . 'FROM ' . $db->quoteName('#__dharmashop_cart_items', 'ci') . ' JOIN ' . $db->quoteName('#__dharmashop_carts', 'c') . ' ON c.id = ci.cart_id '
            . 'LEFT JOIN ' . $db->quoteName('#__dharmashop_variant_prices', 'vp') . ' ON vp.variant_id = ci.variant_id AND vp.price_list_id = c.price_list_id '
            . 'WHERE ci.cart_id IN (' . $ids . ') GROUP BY ci.cart_id'
        )->loadObjectList() as $row) {
            $summary[(int) $row->cart_id] = $row;
        }

        $lines = [];

        foreach ($db->setQuery(
            'SELECT ci.cart_id, ci.qty, p.id AS product_id, p.title FROM ' . $db->quoteName('#__dharmashop_cart_items', 'ci')
            . ' JOIN ' . $db->quoteName('#__dharmashop_variants', 'v') . ' ON v.id = ci.variant_id JOIN ' . $db->quoteName('#__dharmashop_products', 'p') . ' ON p.id = v.product_id'
            . ' WHERE ci.cart_id IN (' . $ids . ') ORDER BY ci.cart_id, ci.id'
        )->loadObjectList() as $row) {
            $lines[(int) $row->cart_id][] = $row;
        }

        foreach ($items as $item) {
            $id = (int) $item->id;
            $item->lines_count = (int) ($summary[$id]->lines_count ?? 0);
            $item->qty_sum = (float) ($summary[$id]->qty_sum ?? 0);
            $item->total_sum = (float) ($summary[$id]->total_sum ?? 0);
            $item->no_price = (int) ($summary[$id]->no_price ?? 0);
            $item->lines = $lines[$id] ?? [];
        }

        return $items;
    }
}
