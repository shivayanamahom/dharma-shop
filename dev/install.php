<?php
// Dev-only: устанавливает пакет из zip в тестовую копию (как «Установка расширений» в админке).
// php dev/install.php dist/pkg_dharmashop-0.1.0.zip   (после установки удалите папку install_* рядом с zip)
require __DIR__ . '/boot.php';
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Installer\InstallerHelper;
$zip = $argv[1] ?? exit("usage: php install.php <package.zip>\n");
$package = InstallerHelper::unpack($zip, true);
echo 'package type: ' . ($package['type'] ?? 'unknown') . "\n";
$installer = new Installer();
$installer->setDatabase(\Joomla\CMS\Factory::getContainer()->get('DatabaseDriver'));
echo $installer->install($package['extractdir']) ? "INSTALL OK\n" : "INSTALL FAILED\n";
foreach (\Joomla\CMS\Factory::getApplication()->getMessageQueue(true) as $m) {
    echo '[' . ($m['type'] ?? '') . '] ' . strip_tags((string) ($m['message'] ?? '')) . "\n";
}
// Рабочая папка распаковки лежит рядом с zip — убираем.
if (!empty($package['extractdir']) && is_dir($package['extractdir'])) {
    exec('rm -rf ' . escapeshellarg($package['extractdir']));
}
