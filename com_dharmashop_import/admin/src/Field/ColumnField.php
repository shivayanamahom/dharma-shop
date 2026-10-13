<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShopImport\Administrator\Field;
use Joomla\CMS\HTML\HTMLHelper;
\defined('_JEXEC') or die;
class ColumnField extends \Joomla\CMS\Form\Field\ListField
{
    protected $type='Column';
    protected function getOptions(){
        $p=ProfileContext::get()['params'];$options=[];
        foreach($p['headers']??[] as $i=>$header){$sample=mb_substr((string)($p['example'][$i]??''),0,60);$options[]=HTMLHelper::_('select.option',(string)$i,($i+1).'. '.$header.($sample!==''?' — '.$sample:''));}
        return array_merge(parent::getOptions(),$options);
    }
}
