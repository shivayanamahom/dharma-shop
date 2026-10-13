<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShopImport\Administrator\Field;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
\defined('_JEXEC') or die;
class SheetField extends \Joomla\CMS\Form\Field\ListField
{
    protected $type='Sheet';
    protected function getOptions()
    {
        $profile=ProfileContext::get();$options=[];
        foreach((array)($profile['params']['xlsx_sheets']??[]) as $sheet){$options[]=HTMLHelper::_('select.option',(string)$sheet,(string)$sheet);}
        if(!$options){$options[]=HTMLHelper::_('select.option','',Text::_('COM_DHARMASHOP_IMPORT_XLSX_UPLOAD_FIRST'));}
        return array_merge(parent::getOptions(),$options);
    }
}
