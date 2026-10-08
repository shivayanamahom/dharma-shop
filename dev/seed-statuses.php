<?php
// Dev-only: добавляет стандартные статусы заказа на тестовом сайте (то же, что пакет делает при первой установке), недостающие по алиасу.
//   php dev/seed-statuses.php
require __DIR__ . '/boot.php';
$db = \Joomla\CMS\Factory::getContainer()->get('DatabaseDriver');
$language = \Joomla\CMS\Factory::getApplication()->getLanguage();
$language->load('com_dharmashop', JPATH_ADMINISTRATOR, 'ru-RU', true);
require_once JPATH_ADMINISTRATOR . '/components/com_dharmashop/src/Order/DefaultStatuses.php';
echo 'добавлено статусов: ', \Dharma\Component\DharmaShop\Administrator\Order\DefaultStatuses::install($db, false), "\n";
