<?php
// Dev-only: устанавливает модуль из папки репозитория штатным установщиком Joomla (создаёт запись расширения) и, если указан
// второй аргумент, — публикует его экземпляр в позиции шаблона.
//   php dev/install-module.php mod_dharmashop_search [позиция]
require __DIR__ . '/boot.php';
$name = $argv[1] ?? '';
$path = dirname(__DIR__) . '/' . $name;
if ($name === '' || !is_file($path . '/' . $name . '.xml')) { exit("укажите папку модуля\n"); }
$installer = \Joomla\CMS\Installer\Installer::getInstance();
$installer->setPath('source', $path);
$ok = $installer->install($path);
echo $ok ? "установлен: {$name}\n" : "ошибка установки\n";
if ($ok && !empty($argv[2])) {
    $db = \Joomla\CMS\Factory::getContainer()->get('DatabaseDriver');
    $exists = (int) $db->setQuery($db->getQuery(true)->select('COUNT(*)')->from('#__modules')->where('module = ' . $db->quote($name)))->loadResult();
    if (!$exists) {
        $row = (object) ['title' => 'Поиск по каталогу', 'module' => $name, 'position' => $argv[2], 'published' => 1, 'access' => 1, 'showtitle' => 0, 'params' => '{"module_tag":"div","style":"0"}', 'client_id' => 0, 'language' => '*', 'ordering' => 1];
        $db->insertObject('#__modules', $row, 'id');
        $db->insertObject('#__modules_menu', (object) ['moduleid' => $row->id, 'menuid' => 0]);
        echo "модуль создан: id={$row->id}, позиция {$argv[2]}\n";
    }
}
