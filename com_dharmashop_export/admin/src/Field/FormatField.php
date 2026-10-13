<?php
/** Dharma Shop — экспорт каталога. Copyright (C) 2026 Dharma. GPL-2.0-or-later. */
declare(strict_types=1);

namespace Dharma\Component\DharmaShopExport\Administrator\Field;
use Dharma\Component\DharmaShopExport\Administrator\Export\Formats;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
\defined('_JEXEC') or die;
class FormatField extends ListField {
 protected $type='Format';
 protected function getOptions():array { $out=[];foreach((new Formats())->all() as $key=>$item){$out[]=HTMLHelper::_('select.option',$key,$item['label']);}return $out; }
}
