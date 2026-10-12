<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Filter;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\Database\QueryInterface;

\defined('_JEXEC') or die;

/** MySQL SELECT hint, без изменения сессии соединения и без пользовательского SQL. */
final class QueryBudget
{
    /** MySQL error codes of an interrupted statement (MySQL 3024, MariaDB 1969). */
    public static function isTimeout(\Throwable $error): bool
    {
        return in_array((int) $error->getCode(), [3024, 1969], true) || str_contains($error->getMessage(), 'maximum statement execution time');
    }

    /**
     * Protection for queries the page cannot do without (list, unfiltered total): a runaway limit, not the facet budget.
     * The tight `filter_query_ms` budget is meant for optional parts that can degrade; applying it to the list turned slow
     * queries into an exception page.
     */
    public static function hard(QueryInterface $query): QueryInterface
    {
        $budget = (int) ComponentHelper::getParams('com_dharmashop')->get('filter_query_ms', 1500);

        return self::apply($query, max(10000, 6 * max(50, min(10000, $budget))));
    }

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
