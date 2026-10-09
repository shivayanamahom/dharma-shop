<?php
\defined('_JEXEC') or die;

use Dharma\Component\DharmaShop\Administrator\Image\ImageUrl;

$escape = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$color = preg_match('/^#[0-9a-f]{6}$/iD', (string) ($displayData['color'] ?? '')) ? (string) $displayData['color'] : '';
$image = $color === '' ? ImageUrl::resolve((string) ($displayData['image'] ?? ''), (string) ($displayData['context'] ?? 'field.color.page')) : '';
$label = (string) ($displayData['label'] ?? '');
$round = !empty($displayData['round']);
$shape = $round ? 'rounded-circle' : 'rounded';
?>
<?php if ($color !== '') : ?>
    <svg class="border <?php echo $shape; ?> flex-shrink-0" width="32" height="32" viewBox="0 0 32 32"<?php echo $label === '' ? ' aria-hidden="true"' : ' role="img" aria-label="' . $escape($label) . '"'; ?> focusable="false"><?php if ($round) : ?><circle cx="16" cy="16" r="16" fill="<?php echo $escape($color); ?>" /><?php else : ?><rect width="32" height="32" fill="<?php echo $escape($color); ?>" /><?php endif; ?></svg>
<?php elseif ($image !== '') : ?>
    <img class="border <?php echo $shape; ?> object-fit-cover flex-shrink-0" src="<?php echo $escape($image); ?>" width="32" height="32" alt="<?php echo $escape($label); ?>" loading="lazy" decoding="async">
<?php endif; ?>
