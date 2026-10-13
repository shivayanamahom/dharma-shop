<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShopImport\Administrator\Field;
use Joomla\CMS\Factory;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Language\Text;
use Dharma\Component\DharmaShopImport\Administrator\Import\Storage;
\defined('_JEXEC') or die;
/** Reuses the existing gallery transport; source bytes are never public attachments. */
class SourceField extends \Joomla\CMS\Form\FormField
{
    protected $type='Source';
    protected function getInput():string{
        $wa=Factory::getApplication()->getDocument()->getWebAssetManager();$wa->getRegistry()->addExtensionRegistryFile('com_dharmashop');$wa->useScript('com_dharmashop.gallery')->useStyle('com_dharmashop.gallery');
        $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $card=function($i,$item)use($e){return '<li class="dharmashop-gallery__item"><input type="hidden" data-field="path" name="'.$e($this->name).'['.$i.'][path]" value="'.$e($item['path']??'').'"><span class="icon-file me-1" aria-hidden="true"></span><span class="dharmashop-gallery__filename">'.$e($item['filename']??'').'</span><small class="dharmashop-gallery__details d-block text-muted">'.$e(isset($item['bytes'])?round($item['bytes']/1024,1).' KiB':'').'</small><button type="button" class="dharmashop-gallery__remove btn btn-sm btn-danger" aria-label="'.$e(Text::_('JREMOVE')).'"><span class="icon-times" aria-hidden="true"></span></button></li>';};
        $items='';foreach((array)$this->value as $i=>$v){if(!empty($v['path'])){$items.=$card((string)$i,(array)$v);}}
        return '<div id="'.$e($this->id).'" class="dharmashop-gallery" data-kind="files" data-name="'.$e($this->name).'" data-url="index.php?option=com_dharmashop_import&amp;task=job.upload&amp;format=json" data-token="'.Session::getFormToken().'" data-max-count="1" data-max-bytes="'.Storage::uploadMaxBytes().'" data-accept="csv,xlsx" data-error-count="'.$e(Text::_('COM_DHARMASHOP_IMPORT_ONE_FILE')).'" data-error-type="'.$e(Text::_('COM_DHARMASHOP_IMPORT_FORMAT_ERROR')).'" data-error-size="'.$e(Text::_('COM_DHARMASHOP_IMPORT_SIZE')).'" data-error-server="'.$e(Text::_('COM_DHARMASHOP_IMPORT_UPLOAD')).'">'
            .'<input type="hidden" name="'.$e($this->name).'[_][path]" value=""><input type="file" class="dharmashop-gallery__file visually-hidden" accept=".csv,.xlsx" tabindex="-1" aria-hidden="true">'
            .'<div class="dharmashop-gallery__drop" tabindex="0" role="button"><span class="icon-upload dharmashop-gallery__icon" aria-hidden="true"></span><span class="dharmashop-gallery__title">'.$e(Text::_('COM_DHARMASHOP_IMPORT_DROP')).'</span><button type="button" class="btn btn-link dharmashop-gallery__choose">'.$e(Text::_('COM_DHARMASHOP_IMPORT_CHOOSE')).'</button><small class="dharmashop-gallery__limits">CSV / XLSX · '.round(Storage::uploadMaxBytes()/1048576,1).' MiB</small></div>'
            .'<div class="progress mt-2" hidden><div class="progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"></div></div><ul class="dharmashop-gallery__list">'.$items.'</ul><template class="dharmashop-gallery__tpl">'.$card('__i__',[]).'</template></div>';
    }
}
