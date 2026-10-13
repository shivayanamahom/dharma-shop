<?php
/**
 * Пункты главного меню сайта для сценарной проверки (чистая установка не создаёт пунктов магазина на сайте).
 * Использование: DHARMA_SITE=/путь/к/сайту php dev/scenario/menu.php
 */
require __DIR__ . '/../boot.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;

$db = Factory::getContainer()->get(Joomla\Database\DatabaseInterface::class);
$items = [
    ['Каталог', 'katalog', 'com_dharmashop', 'index.php?option=com_dharmashop&view=categories', ['id' => '']],
    ['Бренды', 'brendy', 'com_dharmashop', 'index.php?option=com_dharmashop&view=brands', []],
    ['Бейджи', 'bejdzhi', 'com_dharmashop', 'index.php?option=com_dharmashop&view=badges', []],
    ['Избранное', 'izbrannoe', 'com_dharmashop', 'index.php?option=com_dharmashop&view=favorites', []],
    ['Корзина', 'korzina', 'com_dharmashop', 'index.php?option=com_dharmashop&view=cart', []],
    ['Оформление заказа', 'oformlenie', 'com_dharmashop', 'index.php?option=com_dharmashop&view=checkout', []],
    ['Заказ оформлен', 'zakaz-oformlen', 'com_dharmashop', 'index.php?option=com_dharmashop&view=checkoutcomplete', []],
    ['Мои заказы', 'moi-zakazy', 'com_dharmashop', 'index.php?option=com_dharmashop&view=orders', []],
    ['Посадочные страницы', 'posadochnye', 'com_dharmashop_landing', 'index.php?option=com_dharmashop_landing&view=landings', []],
];
foreach ($items as [$title, $alias, $component, $link, $request]) {
    $exists = (int) $db->setQuery("SELECT id FROM #__menu WHERE alias = " . $db->quote($alias) . " AND client_id = 0")->loadResult();
    if ($exists) { echo "есть: $title\n"; continue; }
    $extId = (int) $db->setQuery("SELECT extension_id FROM #__extensions WHERE element = " . $db->quote($component) . " AND type = 'component'")->loadResult();
    $table = Table::getInstance('Menu');
    $table->setLocation(1, 'last-child');
    $ok = $table->bind(['menutype' => 'mainmenu', 'title' => $title, 'alias' => $alias, 'link' => $link, 'type' => 'component', 'published' => 1, 'component_id' => $extId, 'access' => 1,
        'language' => '*', 'client_id' => 0, 'params' => '{}', 'parent_id' => 1, 'level' => 1, 'home' => 0]) && $table->check() && $table->store();
    echo ($ok ? 'создан: ' : 'ОШИБКА: ') . $title . ($ok ? " (id {$table->id})" : ' ' . $table->getError()) . "\n";
}
