<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Field;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
\defined('_JEXEC') or die;
final class ExportfolderField extends ListField {
 protected $type='Exportfolder';
 protected function getOptions():array {
  $values=['files/export'=>'files/export'];$base=JPATH_ROOT.'/files';
  if(is_dir($base)&&!is_link($base)) {
   $iterator=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::SELF_FIRST);$iterator->setMaxDepth(3);$n=0;
   foreach($iterator as $file){if(++$n>5000){break;}if($file->isDir()&&!$file->isLink()){$path='files/'.substr($file->getPathname(),strlen($base)+1);if(preg_match('~^files/[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*$~D',$path)&&!str_contains('/'.$path.'/','/private/')){$values[$path]=$path;}}}
  }
  if(preg_match('~^files/[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*$~D',(string)$this->value)){$values[$this->value]=$this->value;}
  ksort($values);$out=[];foreach($values as $v){$out[]=HTMLHelper::_('select.option',$v,$v);}return $out;
 }
}
