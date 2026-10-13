<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Installer;

\defined('_JEXEC') or die;

/**
 * Новые отдельные права при обновлении получают те группы, которые раньше могли то же самое общим правом: иначе после обновления
 * менеджеры потеряли бы доступ. «Управление полями» и «управление группами» наследуют группы права «Править» (`core.edit`);
 * «управление статусами заказов» — группы прежнего права `dharmashop.orders.status` (оно раньше охраняло именно статусы);
 * «смена статуса заказа» (`dharmashop.orders.status`) теперь отдельное право — к нему добавляются группы «Управлять заказами»
 * (раньше менять статус мог любой, кто управляет заказами). Миграция выполняется один раз: признак — наличие в правах компонента
 * действия `dharmashop.statuses.manage` (оно записывается всегда, даже пустым); заданные вручную правки не перезаписываются.
 */
final class AclMigration
{
    /**
     * Версия модели прав, записанная в параметрах компонента (`acl_version`). Установщик ставит её сам и переносит права только при
     * повышении версии: признак «действие есть в правилах» ненадёжен — он исчезает после любого сохранения прав в настройках, и
     * повторный перенос вернул бы группам права, которые владелец отозвал. Версия 1 — модель C90; её перенос выполнили сборки до
     * 0.1.0-dev.1, поэтому установщик больше не вызывает `migrate()` сам.
     */
    public const VERSION = 1;

    /** Новое действие => действие, чьи группы оно получает. */
    public const INHERIT = [
        'dharmashop.fields.manage' => 'core.edit',
        'dharmashop.groups.manage' => 'core.edit',
        'dharmashop.statuses.manage' => 'dharmashop.orders.status',
    ];

    /** @return string  правила в JSON (формат таблицы `#__assets`): `{"действие":{"группа":1,…},…}` */
    public static function migrate(string $rulesJson): string
    {
        $rules = json_decode($rulesJson !== '' ? $rulesJson : '{}', true);

        // Не JSON или уже мигрировано (признак — наличие действия статусов).
        if (!is_array($rules) || array_key_exists('dharmashop.statuses.manage', $rules) || $rules === []) {
            return $rulesJson;
        }

        $statusDefinitions = is_array($rules['dharmashop.orders.status'] ?? null) ? $rules['dharmashop.orders.status'] : [];

        foreach (self::INHERIT as $new => $source) {
            $groups = is_array($rules[$source] ?? null) ? $rules[$source] : [];
            $rules[$new] = $groups ?: new \stdClass();
        }

        // Смена статуса заказа: прежние группы права плюс группы «Управлять заказами».
        $manage = is_array($rules['dharmashop.orders.manage'] ?? null) ? $rules['dharmashop.orders.manage'] : [];
        $rules['dharmashop.orders.status'] = ($statusDefinitions + $manage) ?: new \stdClass();

        return json_encode($rules, JSON_UNESCAPED_SLASHES | JSON_FORCE_OBJECT);
    }
}
