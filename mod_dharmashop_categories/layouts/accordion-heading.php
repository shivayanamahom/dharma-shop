<?php
\defined('_JEXEC') or die;

use Dharma\Component\DharmaShop\Administrator\Image\ImageUrl;

$item = $displayData['item'];
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$source = $displayData['showImage'] ? (string) ($item->params->get('image') ?: $item->params->get('icon', '')) : '';
$image = $source !== '' ? ImageUrl::resolve($source, $displayData['imageContext']) : '';
?>
<?php if ($image !== '') : ?>
    <img class="flex-shrink-0 object-fit-contain rounded" src="<?php echo $escape($image); ?>" width="48" height="48" alt="" loading="lazy" decoding="async">
<?php endif; ?>
<span><?php echo $escape($item->title); ?></span>
