<?php
// Dev-only: создаёт категорию заданного типа на тестовом сайте.
//   php dev/make-category.php <extension> <название> [родитель-id]
//   extension: com_dharmashop | com_dharmashop.brand | com_dharmashop.badge
require __DIR__ . '/boot.php';
$ext = $argv[1] ?? exit("usage: php dev/make-category.php <extension> <title> [parent_id]\n");
$title = $argv[2] ?? exit("нужно название\n");
$parent = (int) ($argv[3] ?? 1);
$db = \Joomla\CMS\Factory::getContainer()->get('DatabaseDriver');
$table = new \Joomla\CMS\Table\Category($db);
$table->setLocation($parent, 'last-child');
$ok = $table->bind([
    'title' => $title, 'alias' => \Joomla\CMS\Application\ApplicationHelper::stringURLSafe($title), 'extension' => $ext, 'published' => 1, 'access' => 1,
    'language' => '*', 'description' => '', 'params' => '{}', 'metadata' => '{}', 'created_user_id' => 0,
]) && $table->check() && $table->store();
if (!$ok) { exit('ошибка: ' . $table->getError() . "\n"); }
$table->rebuildPath($table->id);
echo "создана категория id={$table->id} ($ext)\n";
