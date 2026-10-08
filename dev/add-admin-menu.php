<?php
// Dev-only: добавляет пункт в меню компонента в админке (то же, что делает установщик по <submenu> манифеста) на тестовом сайте.
//   php dev/add-admin-menu.php COM_DHARMASHOP_MENU_CARTS "index.php?option=com_dharmashop&view=carts" com-dharmashop-carts
require __DIR__ . '/boot.php';
[, $title, $link, $alias] = $argv + [null, null, null, null];
if (!$title || !$link || !$alias) { exit("укажите заголовок, ссылку и алиас\n"); }
$db = \Joomla\CMS\Factory::getContainer()->get('DatabaseDriver');
$exists = (int) $db->setQuery($db->getQuery(true)->select('COUNT(*)')->from('#__menu')->where('client_id = 1')->where('link = ' . $db->quote($link)))->loadResult();
if ($exists) { exit("пункт уже есть\n"); }
$parent = (int) $db->setQuery($db->getQuery(true)->select('id')->from('#__menu')->where('client_id = 1')->where('link = ' . $db->quote('index.php?option=com_dharmashop'))->where('parent_id = 1'))->loadResult();
$component = (int) $db->setQuery($db->getQuery(true)->select('extension_id')->from('#__extensions')->where('type = ' . $db->quote('component'))->where('element = ' . $db->quote('com_dharmashop')))->loadResult();
$table = new \Joomla\CMS\Table\Menu($db);
$table->setLocation($parent, 'last-child');
$ok = $table->bind(['menutype' => 'main', 'title' => $title, 'alias' => $alias, 'path' => $alias, 'link' => $link, 'type' => 'component', 'published' => 1,
    'parent_id' => $parent, 'level' => 2, 'component_id' => $component, 'client_id' => 1, 'access' => 1, 'language' => '*', 'img' => 'class:cart', 'params' => '{}'])
    && $table->check() && $table->store();
echo $ok ? "добавлен: {$title} (id {$table->id})\n" : 'ошибка: ' . $table->getError() . "\n";
