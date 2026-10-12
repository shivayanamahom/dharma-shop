<?php
/** Только собственная временная копия модуля на test.test; исходный модуль не меняется. */
require __DIR__ . '/boot.php';
if (realpath(JPATH_ROOT) !== '/Volumes/Work/Server/www/test') { throw new RuntimeException('Only test.test is allowed'); }
use Joomla\CMS\Factory;
$db = Factory::getContainer()->get(Joomla\Database\DatabaseInterface::class);
$app = Factory::getApplication();
$env = parse_ini_file(__DIR__ . '/.env');
$uid = (int) $db->setQuery('SELECT id FROM #__users WHERE username=' . $db->quote($env['DHARMA_USER']))->loadResult();
$app->loadIdentity(Factory::getContainer()->get(Joomla\CMS\User\UserFactoryInterface::class)->loadUserById($uid));
$model = $app->bootComponent('com_modules')->getMVCFactory()->createModel('Module', 'Administrator', ['ignore_request' => true]);
$path = sys_get_temp_dir() . '/dharma-cascade-fixture.json';
$mode = $argv[1] ?? 'create';
if ($mode === 'create') {
    $table = $model->getTable(); $table->load(127); $data = $table->getProperties();
    $data['id'] = 0; $data['checked_out'] = null; $data['checked_out_time'] = null;
    $data['title'] = 'QA: каскад фильтра'; $data['position'] = 'dharmashop-products-left';
    $data['assignment'] = 0; $data['published'] = 1; $data['showtitle'] = 0;
    $params = json_decode($data['params'], true);
    $params = array_replace($params, ['orientation'=>'vertical','offcanvas_mode'=>'never','cascade'=>'on','empty_options'=>'disable','counts'=>'off','apply_mode'=>'button','ajax'=>1,'show_apply_button'=>1]);
    $data['params'] = $params;
    if (!$model->save($data)) { throw new RuntimeException($model->getError()); }
    $id = (int) $model->getState('module.id');
    file_put_contents($path, json_encode(['id'=>$id,'title'=>$data['title']]));
    echo "Created QA module $id\n";
} else {
    $saved = json_decode(file_get_contents($path),true); $id = (int)$saved['id'];
    $table = $model->getTable(); $table->load($id);
    if ($table->title !== $saved['title']) { throw new RuntimeException('Fixture ownership mismatch'); }
    if ($mode === 'trash' || $mode === 'publish') {
        $ids = [$id]; if (!$model->publish($ids,$mode === 'trash' ? -2 : 1)) { throw new RuntimeException($model->getError()); }
        echo "QA module $id: $mode\n";
    } else {
        $data=$table->getProperties(); $data['params']=json_decode($data['params'],true); $data['assignment']=0;
        foreach (array_slice($argv,2) as $pair) { [$key,$value]=explode('=',$pair,2); $key === 'position' ? $data['position']=$value : $data['params'][$key]=$value; }
        if (!$model->save($data)) { throw new RuntimeException($model->getError()); }
        echo "Updated QA module $id\n";
    }
}
