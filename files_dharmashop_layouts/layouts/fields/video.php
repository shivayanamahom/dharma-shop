<?php
/** Override: templates/<template>/html/layouts/components/dharmashop/fields/video.php */
\defined('_JEXEC') or die;

use Dharma\Component\DharmaShop\Site\Support\FieldGallery;
use Joomla\CMS\Layout\FileLayout;

$field = $displayData['field'];
if (empty($field->values)) { return; }
$settings = (array) ($field->video_settings ?? []);
$field->gallery_settings = [
    'gallery_layout'=>$settings['video_layout'] ?? 'grid',
    'gallery_lightbox'=>$settings['video_lightbox'] ?? 1,
    'gallery_caption'=>$settings['video_caption'] ?? 1,
    'gallery_columns_xs'=>$settings['video_columns_xs'] ?? 1,
    'gallery_columns_sm'=>$settings['video_columns_sm'] ?? 1,
    'gallery_columns_md'=>$settings['video_columns_md'] ?? 2,
    'gallery_columns_lg'=>$settings['video_columns_lg'] ?? 2,
    'gallery_columns_xl'=>$settings['video_columns_xl'] ?? 3,
    'gallery_columns_xxl'=>$settings['video_columns_xxl'] ?? 3,
];
$displayData['gallery'] = FieldGallery::prepare($field);
echo (new FileLayout('components.dharmashop.fields.video.' . $displayData['gallery']['layout'], null,
    ['component'=>'none','client'=>0]))->render($displayData);
