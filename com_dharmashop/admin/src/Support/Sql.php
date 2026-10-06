<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Support;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

\defined('_JEXEC') or die;

/**
 * Готовый SQL с именованными параметрами. У драйвера Joomla нет bind(): параметры привязываются к объекту запроса,
 * поэтому «сырую» строку сначала заворачиваем в запрос.
 *
 *     Sql::exec($db, 'UPDATE #__t SET a = :a WHERE id = :id', [':a' => [$a, ParameterType::STRING], ':id' => [$id, ParameterType::INTEGER]]);
 */
final class Sql
{
    /** @param array<string, array{0: mixed, 1?: string}> $params имя => [значение, тип ParameterType] */
    public static function query(DatabaseInterface $db, string $sql, array $params = []): QueryInterface
    {
        $query = $db->createQuery();
        $query->setQuery($sql);

        foreach ($params as $name => &$param) {
            $query->bind($name, $param[0], $param[1] ?? ParameterType::STRING);
        }

        unset($param);

        return $query;
    }

    /** @param array<string, array{0: mixed, 1?: string}> $params */
    public static function exec(DatabaseInterface $db, string $sql, array $params = []): void
    {
        $db->setQuery(self::query($db, $sql, $params))->execute();
    }
}
