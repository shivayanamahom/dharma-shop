<?php
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$hideEmpty = (int) $params->get('hide_empty_badge', 1) === 1;
if (empty($enabled)) {
    return;
}
?>
<a class="d-inline-flex align-items-center justify-content-center position-relative ps-2 dharmaFavoritesModule" href="<?php echo $escape($favoritesUrl); ?>" title="<?php echo $escape(Text::_('MOD_DHARMASHOP_FAVORITES_TITLE')); ?>" aria-label="<?php echo $escape(Text::_('MOD_DHARMASHOP_FAVORITES_TITLE')); ?>">
    <?php echo $favoriteIcon; ?>
    <span class="position-absolute top-0 start-100 translate-middle badge text-bg-primary rounded-pill<?php echo $hideEmpty && $count === 0 ? ' d-none' : ''; ?>" data-dharmashop-favorites-count<?php echo $hideEmpty ? ' data-hide-empty' : ''; ?>><?php echo (int) $count; ?></span>
</a>
