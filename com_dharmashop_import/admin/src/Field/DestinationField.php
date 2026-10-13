<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShopImport\Administrator\Field;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Dharma\Component\DharmaShopImport\Administrator\Import\Schema;
\defined('_JEXEC') or die;
class DestinationField extends \Joomla\CMS\Form\Field\ListField
{
    protected $type='Destination';
    private static ?array $fields=null;
    protected function getOptions(){
        self::$fields??=(new Schema(Factory::getContainer()->get(DatabaseInterface::class)))->fields(ProfileContext::get()['entity']);$options=[];
        foreach(self::$fields as $key=>$field){$options[]=HTMLHelper::_('select.option',$key,$field['label']);}
        if(in_array(ProfileContext::get()['entity'],['products','categories'],true)){$options[]=HTMLHelper::_('select.option','__new_field',\Joomla\CMS\Language\Text::_('COM_DHARMASHOP_IMPORT_NEW_FIELD'));}
        return array_merge(parent::getOptions(),$options);
    }
}
