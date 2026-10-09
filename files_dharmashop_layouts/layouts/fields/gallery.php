<?php
/** Override: templates/<template>/html/layouts/components/dharmashop/fields/gallery.php */
\defined('_JEXEC') or die;

use Dharma\Component\DharmaShop\Site\Support\FieldGallery;
use Joomla\CMS\Layout\FileLayout;

if (empty($displayData['field']->values)) { return; }
$displayData['gallery'] = FieldGallery::prepare($displayData['field']);
echo (new FileLayout('components.dharmashop.fields.gallery.' . $displayData['gallery']['layout'], null,
    ['component'=>'none', 'client'=>0]))->render($displayData);
