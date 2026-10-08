<?php
declare(strict_types=1);

namespace Dharma\Plugin\System\DharmaShopMenu\Extension;

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

    /** Группы пунктов: [заголовок, ссылка]; между группами — разделитель. */
    private const GROUPS = [
        [
            ['COM_DHARMASHOP_MENU_ORDERS', 'index.php?option=com_dharmashop&view=orders'],
            ['COM_DHARMASHOP_MENU_CARTS', 'index.php?option=com_dharmashop&view=carts'],
            ['COM_DHARMASHOP_MENU_CUSTOMERS', 'index.php?option=com_dharmashop&view=customers'],
        ],
        [
            ['COM_DHARMASHOP_MENU_PRODUCTS', 'index.php?option=com_dharmashop&view=products'],
            ['COM_DHARMASHOP_MENU_CATEGORIES', 'index.php?option=com_dharmashop&view=categories&extension=com_dharmashop'],
            ['COM_DHARMASHOP_MENU_BRANDS', 'index.php?option=com_dharmashop&view=categories&extension=com_dharmashop.brand'],
            ['COM_DHARMASHOP_MENU_BADGES', 'index.php?option=com_dharmashop&view=categories&extension=com_dharmashop.badge'],
        ],
        [
            ['COM_DHARMASHOP_MENU_FIELDS', 'index.php?option=com_dharmashop&view=fields'],
            ['COM_DHARMASHOP_MENU_FIELDGROUPS', 'index.php?option=com_dharmashop&view=fieldgroups'],
        ],
        [
            ['COM_DHARMASHOP_MENU_STATUSES', 'index.php?option=com_dharmashop&view=statuses'],
            ['COM_DHARMASHOP_MENU_SHIPPING', 'index.php?option=com_dharmashop&view=shippings'],
            ['COM_DHARMASHOP_MENU_WAREHOUSES', 'index.php?option=com_dharmashop&view=warehouses'],
            ['COM_DHARMASHOP_MENU_SETTINGS', 'index.php?option=com_config&view=component&component=com_dharmashop'],
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
        $container->getParams()->set('hideitems', array_values(array_unique($hidden)));

        $this->getApplication()->getLanguage()->load(self::ELEMENT . '.sys', JPATH_ADMINISTRATOR);

        $root = new AdministratorMenuItem([
            'title' => 'COM_DHARMASHOP', 'alias' => 'dharmashop-root', 'type' => 'heading', 'link' => '', 'class' => 'class:cart',
            'element' => self::ELEMENT, 'scope' => 'default',
        ]);

        foreach (self::GROUPS as $number => $group) {
            if ($number > 0) {
                $root->addChild(new AdministratorMenuItem(['title' => '', 'type' => 'separator', 'link' => '', 'scope' => 'default']));
            }

            foreach ($group as [$title, $link]) {
                $root->addChild(new AdministratorMenuItem(['title' => $title, 'type' => 'component', 'link' => $link, 'scope' => 'default']));
            }
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
}
