<?php
/** Кнопка избранного поверх изображения карточки товара. */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$item = $displayData['item'] ?? null;
if (!$item || empty($displayData['favoritesEnabled'])) {
    return;
}
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$tokenName = (string) ($displayData['favoritesTokenName'] ?? '');
$favorite = !empty($displayData['favorite']);
$activeClass = preg_replace('/[^a-z0-9_-]/i', '', (string) ($displayData['favoriteActiveClass'] ?? 'text-danger')) ?: 'text-danger';
$formClass = (string) ($displayData['favoriteFormClass'] ?? 'position-absolute top-0 end-0 m-2 z-2');
$buttonClass = (string) ($displayData['favoriteButtonClass'] ?? 'btn btn-light rounded-circle shadow-sm d-inline-flex align-items-center justify-content-center p-2 lh-1');
?>
<form class="<?php echo $escape($formClass); ?>" method="post" action="<?php echo $escape(Route::_('index.php?option=com_dharmashop&task=favorites.toggle', false)); ?>">
    <input type="hidden" name="product_id" value="<?php echo (int) $item->id; ?>">
    <input type="hidden" name="<?php echo $escape($tokenName); ?>" value="1">
    <button type="submit" class="<?php echo $escape($buttonClass . ($favorite ? ' ' . $activeClass : '')); ?>" data-favorites-toggle data-product-id="<?php echo (int) $item->id; ?>" data-token-name="<?php echo $escape($tokenName); ?>" data-favorite-active-class="<?php echo $escape($activeClass); ?>" aria-label="<?php echo $escape(Text::_('COM_DHARMASHOP_FAVORITES_TOGGLE')); ?>" aria-pressed="<?php echo $favorite ? 'true' : 'false'; ?>">
        <?php echo (string) ($displayData['favoriteIcon'] ?? ''); ?>
    </button>
</form>
