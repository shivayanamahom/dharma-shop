<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Order;

use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * Стандартный набор статусов заказа, который ставится вместе с пакетом: название и текст покупателю берутся из языкового файла
 * (магазин одноязычный — значения записываются в базу на языке установки и дальше правятся в «Статусы заказов»). Начальный статус
 * «Новый» создаёт `OrderService::ensureInitialStatus`; остальные добавляются только в пустой справочник, чтобы не возвращать то, что
 * владелец магазина уже изменил или опубликовал иначе.
 */
final class DefaultStatuses
{
    /** alias => [цвет, эффект на остатки (1 — возвращает), конечный статус] в порядке справочника. */
    public const SET = [
        'new' => ['info', 0, 0],
        'processing' => ['primary', 0, 0],
        'awaiting_payment' => ['warning', 0, 0],
        'paid' => ['success', 0, 0],
        'shipped' => ['primary', 0, 0],
        'delivered' => ['success', 0, 0],
        'completed' => ['success', 0, 1],
        'cancelled' => ['danger', 1, 1],
        'returned' => ['dark', 0, 1],
    ];

    /**
     * Добавляет стандартные статусы. По умолчанию — только если в справочнике нет ничего, кроме начального «Новый»; с $onlyWhenEmpty = false
     * добавляются недостающие по алиасу (для обновления уже работающего магазина). Возвращает число добавленных.
     */
    public static function install(DatabaseInterface $db, bool $onlyWhenEmpty = true): int
    {
        $table = $db->quoteName('#__dharmashop_statuses');

        if ($onlyWhenEmpty && (int) $db->setQuery('SELECT COUNT(*) FROM ' . $table)->loadResult() > 1) {
            return 0;
        }

        $existing = $db->setQuery('SELECT alias FROM ' . $table)->loadColumn();
        $added = 0;
        $order = 0;

        foreach (self::SET as $alias => [$color, $stock, $final]) {
            $order++;

            if (in_array($alias, $existing, true) || $alias === 'new' && $existing) {
                continue;
            }

            $key = 'COM_DHARMASHOP_STATUS_DEFAULT_' . strtoupper($alias);
            $title = Text::_($alias === 'new' ? 'COM_DHARMASHOP_ORDER_STATUS_NEW' : $key . '_TITLE');
            $message = $alias === 'new' ? '' : Text::_($key . '_MESSAGE');
            $isDefault = $alias === 'new' ? 1 : 0;
            $query = $db->createQuery()->insert($table)
                ->columns($db->quoteName(['title', 'alias', 'color', 'is_default', 'is_final', 'stock_effect', 'customer_message', 'ordering', 'state']))
                ->values(':title, :alias, :color, :default, :final, :stock, :message, :ordering, 1')
                ->bind(':title', $title)->bind(':alias', $alias)->bind(':color', $color)->bind(':default', $isDefault, \Joomla\Database\ParameterType::INTEGER)
                ->bind(':final', $final, \Joomla\Database\ParameterType::INTEGER)->bind(':stock', $stock, \Joomla\Database\ParameterType::INTEGER)
                ->bind(':message', $message)->bind(':ordering', $order, \Joomla\Database\ParameterType::INTEGER);
            $db->setQuery($query)->execute();
            $added++;
        }

        return $added;
    }
}
