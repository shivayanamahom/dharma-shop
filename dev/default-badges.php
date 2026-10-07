<?php
// Dev-only: то же, что делает установщик пакета (script.php::ensureDefaultBadges) — для сайта, где пакет не переустанавливают.
// Создаёт «Хит», «Новинка», «Акция», если бейджей нет, и записывает sale_badge_id в настройки.
//   php dev/default-badges.php
require __DIR__ . '/boot.php';
$db = \Joomla\CMS\Factory::getContainer()->get('DatabaseDriver');
$ext = 'com_dharmashop.badge';
if ((int) $db->setQuery($db->getQuery(true)->select('COUNT(*)')->from('#__categories')->where('extension = ' . $db->quote($ext)))->loadResult() > 0) {
    exit("бейджи уже есть — ничего не сделано\n");
}
$saleId = 0;
foreach ([['Хит', 'khit', 'danger'], ['Новинка', 'novinka', 'success'], ['Акция', 'akciya', 'warning']] as [$title, $alias, $color]) {
    $table = new \Joomla\CMS\Table\Category($db);
    $table->setLocation(1, 'last-child');
    $ok = $table->bind(['title' => $title, 'alias' => $alias, 'extension' => $ext, 'published' => 1, 'access' => 1, 'language' => '*',
        'description' => '', 'params' => json_encode(['badge_color' => $color, 'badge_show_list' => 1]), 'metadata' => '{}', 'created_user_id' => 0])
        && $table->check() && $table->store();
    if (!$ok) { exit("ошибка: {$title}: " . $table->getError() . "\n"); }
    $table->rebuildPath($table->id);
    if ($alias === 'akciya') { $saleId = (int) $table->id; }
    echo "бейдж {$title}: id={$table->id}\n";
}
$where = 'type = ' . $db->quote('component') . ' AND element = ' . $db->quote('com_dharmashop');
$params = json_decode((string) $db->setQuery($db->getQuery(true)->select('params')->from('#__extensions')->where($where))->loadResult(), true) ?: [];
$params['sale_badge_id'] = $saleId;
$params += ['auto_sale_badge' => 1];
$db->setQuery($db->getQuery(true)->update('#__extensions')->set('params = ' . $db->quote(json_encode($params)))->where($where))->execute();
echo "sale_badge_id={$saleId}\n";
