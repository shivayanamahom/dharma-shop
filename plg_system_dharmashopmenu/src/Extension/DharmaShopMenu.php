<?php
declare(strict_types=1);

namespace Dharma\Plugin\System\DharmaShopMenu\Extension;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\Menu\PreprocessMenuItemsEvent;
use Joomla\CMS\Menu\AdministratorMenuItem;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

\defined('_JEXEC') or die;

/**
 * Отдельный раздел «Dharma Shop» в боковом меню админки: сразу после «Компонентов», с разделителями между группами.
 * Работает через штатное событие onPreprocessMenuItems главного меню (пресет по умолчанию); меню в базе не создаётся.
 * Пункты под «Компонентами» скрываются штатным параметром контейнера (hideitems). Если плагин отключён, меню остаётся прежним.
 */
final class DharmaShopMenu extends CMSPlugin implements SubscriberInterface
{
    protected $allowLegacyListeners = false;

    private const ELEMENT = 'com_dharmashop';

    /**
     * Группы пунктов: [заголовок, ссылка, расширение-условие (или null), действие прав]; между группами — разделитель. Пункт виден, только
     * если расширение установлено и включено и у пользователя есть действие (на самом расширении или на `com_dharmashop`).
     */
    private const GROUPS = [
        [
            ['COM_DHARMASHOP_MENU_ORDERS', 'index.php?option=com_dharmashop&view=orders', null, 'dharmashop.orders.manage'],
            ['COM_DHARMASHOP_MENU_CARTS', 'index.php?option=com_dharmashop&view=carts', null, 'dharmashop.orders.manage'],
            ['COM_DHARMASHOP_MENU_FAVORITES', 'index.php?option=com_dharmashop&view=favorites', null, 'dharmashop.favorites.view'],
            ['COM_DHARMASHOP_MENU_CUSTOMERS', 'index.php?option=com_dharmashop&view=customers', null, 'dharmashop.customers.view'],
        ],
        [
            ['COM_DHARMASHOP_MENU_PRODUCTS', 'index.php?option=com_dharmashop&view=products', null, 'core.manage'],
            ['COM_DHARMASHOP_MENU_PRODUCTGROUPS', 'index.php?option=com_dharmashop&view=productgroups', null, 'dharmashop.groups.manage'],
            ['COM_DHARMASHOP_MENU_CATEGORIES', 'index.php?option=com_dharmashop&view=categories&extension=com_dharmashop', null, 'core.manage'],
            ['COM_DHARMASHOP_MENU_BRANDS', 'index.php?option=com_dharmashop&view=categories&extension=com_dharmashop.brand', null, 'core.manage'],
            ['COM_DHARMASHOP_MENU_BADGES', 'index.php?option=com_dharmashop&view=categories&extension=com_dharmashop.badge', null, 'core.manage'],
        ],
        [
            ['COM_DHARMASHOP_EXPORT', 'index.php?option=com_dharmashop_export&view=profiles', 'com_dharmashop_export', 'core.manage'],
            ['COM_DHARMASHOP_IMPORT', 'index.php?option=com_dharmashop_import&view=profiles', 'com_dharmashop_import', 'core.manage'],
            ['COM_DHARMASHOP_LANDING_MENU', 'index.php?option=com_dharmashop_landing&view=pages', 'com_dharmashop_landing', 'core.manage'],
        ],
        [
            ['COM_DHARMASHOP_MENU_FIELDS', 'index.php?option=com_dharmashop&view=fields', null, 'dharmashop.fields.manage'],
            ['COM_DHARMASHOP_MENU_FIELDGROUPS', 'index.php?option=com_dharmashop&view=fieldgroups', null, 'dharmashop.fields.manage'],
        ],
        [
            ['COM_DHARMASHOP_MENU_STATUSES', 'index.php?option=com_dharmashop&view=statuses', null, 'dharmashop.statuses.manage'],
            ['COM_DHARMASHOP_MENU_SHIPPING', 'index.php?option=com_dharmashop&view=shippings', null, 'dharmashop.shipping.manage'],
            ['COM_DHARMASHOP_MENU_PAYMENT', 'index.php?option=com_dharmashop&view=payments', null, 'dharmashop.payment.manage'],
            ['COM_DHARMASHOP_MENU_PROMOTIONS', 'index.php?option=com_dharmashop&view=promotions', null, 'dharmashop.promotions.manage'],
            ['COM_DHARMASHOP_MENU_WAREHOUSES', 'index.php?option=com_dharmashop&view=warehouses', null, 'dharmashop.warehouses.manage'],
            ['COM_DHARMASHOP_MENU_SETTINGS', 'index.php?option=com_config&view=component&component=com_dharmashop', null, 'core.admin'],
        ],
        [
            ['COM_DHARMASHOP_MENU_UPDATES', 'index.php?option=com_dharmashop&view=updates', null, 'core.admin'],
            ['COM_DHARMASHOP_MENU_HELP', 'index.php?option=com_dharmashop&view=help', null, 'core.manage'],
        ],
    ];

    public static function getSubscribedEvents(): array
    {
        return ['onPreprocessMenuItems' => 'onPreprocessMenuItems'];
    }

    public function onPreprocessMenuItems(PreprocessMenuItemsEvent $event): void
    {
        $params = $event->getParams();

        if ($event->getContext() !== 'com_menus.administrator.module' || ($params !== null && $params->get('menutype', '*') !== '*')) {
            return;
        }

        $items = array_values($event->getItems());
        $position = null;

        foreach ($items as $index => $item) {
            if (($item->alias ?? '') === 'dharmashop-root') {
                return;
            }

            if ($position === null && $item->type === 'container') {
                $position = $index;
            }
        }

        // Уровень без контейнера «Компоненты» (подпункты, другой пресет) не трогаем.
        if ($position === null) {
            return;
        }

        $container = $items[$position];
        $parent = $container->getParent();
        $hidden = (array) ($container->getParams()->get('hideitems') ?: []);
        $hidden[] = self::ELEMENT;

        // Отдельные компоненты магазина (экспорт, посадочные) живут в ветке «Dharma Shop», в «Компонентах» их дубль не нужен.
        foreach (self::GROUPS as $group) {
            foreach ($group as $item) {
                if ($item[2] !== null) {
                    $hidden[] = $item[2];
                }
            }
        }

        $container->getParams()->set('hideitems', array_values(array_unique($hidden)));

        $this->getApplication()->getLanguage()->load(self::ELEMENT . '.sys', JPATH_ADMINISTRATOR);

        $root = new AdministratorMenuItem([
            'title' => 'COM_DHARMASHOP', 'alias' => 'dharmashop-root', 'type' => 'heading', 'link' => '', 'class' => 'class:cart',
            'element' => self::ELEMENT, 'scope' => 'default',
        ]);

        $language = $this->getApplication()->getLanguage();
        $user = $this->getApplication()->getIdentity();
        $number = 0;

        foreach (self::GROUPS as $group) {
            $group = array_values(array_filter($group, static function (array $item) use ($user): bool {
                [, , $extension, $action] = $item + [null, null, null, null];

                return ($extension === null || ComponentHelper::isEnabled($extension)) && ($action === null || $user->authorise($action, $extension ?? self::ELEMENT));
            }));

            if (!$group) {
                continue;
            }

            if ($number++ > 0) {
                $root->addChild(new AdministratorMenuItem(['title' => '', 'type' => 'separator', 'link' => '', 'scope' => 'default']));
            }

            foreach ($group as $item) {
                if ($item[2] !== null) {
                    $language->load($item[2] . '.sys', JPATH_ADMINISTRATOR);
                }

                $props = ['title' => $item[0], 'type' => 'component', 'link' => $item[1], 'scope' => 'default'];

                // Значок с числом найденных обновлений (по сохранённому результату проверки; без обращения в интернет).
                if ($item[0] === 'COM_DHARMASHOP_MENU_UPDATES' && ($pending = $this->pendingUpdates()) > 0) {
                    $props['icon'] = 'image:' . $pending;
                }

                $root->addChild(new AdministratorMenuItem($props));
            }
        }

        // У пользователя нет ни одного пункта магазина — раздела в меню нет.
        if ($number === 0) {
            return;
        }

        // Вставляем после «Компонентов»: добавляем новый узел и переносим следующих соседей в конец.
        $following = \array_slice($items, $position + 1);
        $parent->addChild($root);

        foreach ($following as $item) {
            $parent->addChild($item);
        }

        array_splice($items, $position + 1, 0, [$root]);
        $event->updateItems($items);
    }

    /** Число расширений Dharma Shop с найденным обновлением. */
    private function pendingUpdates(): int
    {
        return class_exists(\Dharma\Component\DharmaShop\Administrator\Extension\ExtensionUpdates::class)
            ? \Dharma\Component\DharmaShop\Administrator\Extension\ExtensionUpdates::pendingCount() : 0;
    }
}
