<?php
// Dev-only: удаляет пакет pkg_dharmashop с тестового сайта (вместе с таблицами и вложенными расширениями).
// php dev/uninstall.php
require __DIR__ . '/boot.php';
use Joomla\CMS\Installer\Installer;
$db = \Joomla\CMS\Factory::getContainer()->get('DatabaseDriver');
$id = (int) $db->setQuery($db->getQuery(true)->select('extension_id')->from('#__extensions')
    ->where('type = ' . $db->quote('package'))->where('element = ' . $db->quote('pkg_dharmashop')))->loadResult();
if (!$id) { exit("pkg_dharmashop не установлен\n"); }
$installer = new Installer();
$installer->setDatabase($db);
echo $installer->uninstall('package', $id) ? "UNINSTALL OK\n" : "UNINSTALL FAILED\n";
foreach (\Joomla\CMS\Factory::getApplication()->getMessageQueue(true) as $m) {
    echo '[' . ($m['type'] ?? '') . '] ' . strip_tags((string) ($m['message'] ?? '')) . "\n";
}
