<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Filter;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\Database\QueryInterface;

\defined('_JEXEC') or die;

/** MySQL SELECT hint, без изменения сессии соединения и без пользовательского SQL. */
final class QueryBudget
{
    public static function apply(QueryInterface $query, ?int $milliseconds = null): QueryInterface
    {
        $ms = max(50, min(10000, $milliseconds ?? (int) ComponentHelper::getParams('com_dharmashop')->get('filter_query_ms', 1500)));
        $select = $query->select;
        if ($select === null) { return $query; }
        $columns = $select->getElements();
        if ($columns && !str_contains((string) $columns[0], 'MAX_EXECUTION_TIME')) {
            $columns[0] = '/*+ MAX_EXECUTION_TIME(' . $ms . ') */ ' . $columns[0];
            $query->clear('select')->select($columns);
        }
        return $query;
    }
}
