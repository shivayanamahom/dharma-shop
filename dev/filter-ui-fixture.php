<?php
/** Render shared UI layouts with 12 isolated values; no catalogue mutation. test.test only. */
require __DIR__ . '/boot.php';
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
$root=JPATH_ROOT;
if (realpath($root)!==realpath('/Volumes/Work/Server/www/test')) { throw new RuntimeException('test.test only'); }
$path=$root.'/tmp/dharma-filter-ui-qa.html';
if (($argv[1]??'')==='remove') { if (is_file($path)) { unlink($path); } echo "Own UI fixture removed\n"; exit; }
Joomla\CMS\Factory::getApplication()->getLanguage()->load('mod_dharmashop_filter',$root);
$options=[];
for ($i=1;$i<=12;$i++) { $options[]=['label'=>'Значение '.$i,'value'=>(string)$i,'checked'=>$i===12,'disabled'=>false,'count'=>$i,'color'=>'#276B5E','image'=>'']; }
$section=['name'=>'filter[f][qa][]','control'=>'checkboxes','selected'=>true,'limit'=>3,'search'=>true,'options'=>$options];
$layout=new FileLayout('mod_dharmashop_filter.control.options');$layout->addIncludePath($root.'/modules/mod_dharmashop_filter/layouts');
$html=$layout->render(['section'=>$section,'module'=>999]);
$range=['name'=>'filter[f][qa-range]','title'=>'Дробный диапазон','slider'=>true,'bounds'=>['min'=>5.0,'max'=>6.8],'value'=>['min'=>null,'max'=>null],'step'=>'any'];
$render=static function($name,$data)use($root){$l=new FileLayout('mod_dharmashop_filter.control.'.$name);$l->addIncludePath($root.'/modules/mod_dharmashop_filter/layouts');return $l->render($data);};
$rangeHtml=$render('range',['section'=>$range,'module'=>999]);
$html.='<hr>'.$rangeHtml.'<hr>'.$render('swatches',['section'=>$section+['swatches'=>true],'module'=>998]);
file_put_contents($path,'<!doctype html><html lang="ru"><meta charset="UTF-8"><title>QA фильтра</title><link rel="stylesheet" href="/media/templates/site/cassiopeia/css/template.css"><link rel="stylesheet" href="/media/mod_dharmashop_filter/css/filter-range.css"><main class="container py-4"><h1>Проверка макетов фильтра</h1><form class="col-lg-6">'.$html.'</form></main><script src="/media/mod_dharmashop_filter/js/filter-values.js"></script><script src="/media/mod_dharmashop_filter/js/filter-slider.js"></script></html>');
echo "Own UI fixture: /tmp/dharma-filter-ui-qa.html\n";
