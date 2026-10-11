<?php
/** Integration check on test.test: native models, real prices/stock/fields. Own records go to trash. */
declare(strict_types=1);
require __DIR__ . '/boot.php';

use Dharma\Component\DharmaShop\Administrator\Catalog\GroupGenerator;
use Dharma\Component\DharmaShop\Administrator\Catalog\GroupService;
use Joomla\CMS\Factory;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;

// Web models use Joomla's editing-session state; initialise a local administrator application.
$app = Factory::getContainer()->get(\Joomla\CMS\Application\AdministratorApplication::class);
Factory::$application = $app;
$app->getSession()->set('registry', new \Joomla\Registry\Registry());
$db = Factory::getContainer()->get(DatabaseInterface::class);
$username = getenv('DHARMA_USER') ?: throw new RuntimeException('Load dev/.env first.');
$userId = (int) $db->setQuery('SELECT id FROM #__users WHERE username=' . $db->quote($username))->loadResult();
$app->loadIdentity(Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userId));
$factory = $app->bootComponent('com_dharmashop')->getMVCFactory();
$newModel = static fn($name) => $factory->createModel($name, 'Administrator', ['ignore_request' => true]);
$groups = new GroupService($db);
$generator = new GroupGenerator($db);
$check = static function (bool $condition, string $name): void {
    if (!$condition) { throw new RuntimeException('FAIL: ' . $name); }
    echo 'PASS: ' . $name . "\n";
};
$reject = static function (callable $action, string $message) use ($check): void {
    try { $action(); } catch (RuntimeException $error) { $check($error->getMessage() === $message, $message); return; }
    throw new RuntimeException('Expected rejection: ' . $message);
};
$axisIds = [];
foreach ($db->setQuery("SELECT id FROM #__dharmashop_fields WHERE state=1 AND type IN ('list','color') AND level=1 AND target='product' ORDER BY id")->loadColumn() as $candidate) {
    try { $groups->validateAxes([(int) $candidate]); } catch (RuntimeException) { continue; }
    if ((int) $db->setQuery('SELECT COUNT(*) FROM #__dharmashop_field_options WHERE field_id=' . (int) $candidate)->loadResult() >= 2) { $axisIds[] = (int) $candidate; }
    if (count($axisIds) === 2) { break; }
}
if (count($axisIds) !== 2) { throw new RuntimeException('Need two eligible axes with at least two values.'); }
$axes = $generator->axes($axisIds);
$catid = (int) $db->setQuery("SELECT id FROM #__categories WHERE extension='com_dharmashop' AND published=1 ORDER BY id", 0, 1)->loadResult();
$images = $db->setQuery("SELECT DISTINCT path FROM #__dharmashop_field_media WHERE field_id=0 AND path<>''", 0, 2)->loadColumn();
if (count($images) < 2) { throw new RuntimeException('Need two existing test images for inheritance checks.'); }
$groupModel = $newModel('Productgroup');
$groupId = 0;
$created = [];
try {
    $data = ['id' => 0, 'title' => 'Проверка генератора ' . bin2hex(random_bytes(4)), 'alias' => '', 'state' => 0,
        'catid' => $catid, 'axes' => $axisIds, 'members' => [], 'introtext' => 'Общее краткое описание', 'fulltext' => '<p>Общее полное описание</p>',
        'gallery' => [['path' => $images[0], 'alt' => 'Общее фото']], 'title_template' => '{group.title} {variant.values}'];
    $check($groupModel->save($data), 'save group: ' . $groupModel->getError());
    $groupId = (int) $groupModel->getState('productgroup.id');
    foreach ($axes as $axis) { $data['generation_values']['f_' . $axis->id] = array_slice(array_keys($axis->options), 0, 2); }
    $data['generation_price'] = 1234.50;
    $data['generation_stock'] = 7.5;
    $draft = $generator->prepare($groupId, $data);
    $check($draft['added'] === 4 && count($groups->members($groupId)) === 0, '2 x 2 preview writes no products');
    $data['executions'] = $draft['rows'];
    $again = $generator->prepare($groupId, $data);
    $check($again['added'] === 0 && $again['skipped'] === 4, 'prepare preserves draft without duplicates');
    $data['revision'] = (int) $groups->get($groupId)->revision;
    $data['executions'][0]['custom_title'] = $data['title'];
    $data['executions'][1]['custom_title'] = $data['title'];
    $data['executions'][0]['image'] = $images[1];
    $data['executions'][1]['price'] = 999;
    $data['executions'][1]['product_state'] = 0;
    $bad = $data; $bad['revision']--;
    $reject(fn() => $generator->plan($groupId, $bad), 'COM_DHARMASHOP_GROUP_CHANGED');
    $bad = $data; $bad['executions'][1] = $bad['executions'][0];
    $reject(fn() => $generator->plan($groupId, $bad), 'COM_DHARMASHOP_GROUP_DUPLICATE');
    $bad = $data; $bad['executions'][2]['price'] = -1;
    $reject(fn() => $generator->plan($groupId, $bad), 'COM_DHARMASHOP_GROUP_GENERATION_REQUIRED');
    $bad = $data; $bad['executions'][2]['axis_' . $axisIds[0]] = 999999;
    $reject(fn() => $generator->plan($groupId, $bad), 'COM_DHARMASHOP_GROUP_COMBINATION_REQUIRED');
    $bad = $data; $bad['executions'] = array_fill(0, 101, $draft['rows'][0]);
    $reject(fn() => $generator->plan($groupId, $bad), 'COM_DHARMASHOP_GENERATOR_LIMIT');
    $reject(fn() => $generator->title('{unknown.code}', 'name', $axes, []), 'COM_DHARMASHOP_GENERATOR_TEMPLATE_INVALID');
    $check(count($groups->members($groupId)) === 0, 'invalid batches create no products');
    $result = $groupModel->createExecutions($groupId, $data);
    $created = $result['created'];
    $check(count($created) === 4 && $result['error'] === '', 'create four ordinary products: ' . $result['error']);
    $rows = $db->setQuery('SELECT p.id,p.title,p.alias,p.state,p.cover,p.inherit_images,p.inherit_intro,p.price_min,v.sku,v.stock_total FROM #__dharmashop_products p JOIN #__dharmashop_variants v ON v.product_id=p.id WHERE p.group_id=' . $groupId . ' ORDER BY p.id')->loadAssocList();
    $check(count($rows) === 4 && count(array_unique(array_column($rows, 'sku'))) === 4, 'exactly one SKU per product, all unique');
    $check($rows[1]['alias'] === $rows[0]['alias'] . '-2', 'same names receive unique aliases');
    $check($rows[0]['cover'] === $images[1] && $rows[0]['inherit_images'] === 'own', 'custom image wins');
    $check($rows[1]['cover'] === $images[0] && $rows[1]['inherit_images'] === 'group', 'group image inherited');
    $check((float) $rows[0]['stock_total'] === 7.5 && (float) $rows[1]['price_min'] === 999.0, 'stock and custom price persisted');
    $check((int) $rows[0]['state'] === 1 && (int) $rows[1]['state'] === 0, 'published default and manual unpublished override');
    $product = $db->setQuery('SELECT id,group_id,introtext,`fulltext`,inherit_intro,inherit_full,inherit_images FROM #__dharmashop_products WHERE id=' . $created[1])->loadObject();
    $groups->inherit($product);
    $check($product->introtext === $data['introtext'] && $product->fulltext === $data['fulltext'], 'descriptions inherited');
    $fieldCount = (int) $db->setQuery('SELECT COUNT(*) FROM #__dharmashop_field_values WHERE product_id IN (' . implode(',', $created) . ') AND field_id IN (' . implode(',', $axisIds) . ')')->loadResult();
    $check($fieldCount === 8, 'choice values stored as ordinary product fields');
    $data['revision'] = (int) $groups->get($groupId)->revision;
    $repeat = $groupModel->createExecutions($groupId, $data);
    $check(!$repeat['created'] && $repeat['skipped'] === 4 && count($groups->members($groupId)) === 4, 'repeat generation skips existing combinations');
} finally {
    if ($groupId) {
        $ids = array_map(static fn($p) => (int) $p->id, $groups->members($groupId));
        if ($ids) { $newModel('Product')->publish($ids, -2); }
        $ids = [$groupId]; $newModel('Productgroup')->publish($ids, -2);
        echo 'Own test group ' . $groupId . " and its products moved to trash.\n";
    }
}
