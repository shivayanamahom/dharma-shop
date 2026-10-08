<?php
/**
 * Значок корзины: ссылка на страницу корзины, число позиций и сумма. Элементы с data-dharmashop-cart-count и
 * data-dharmashop-cart-total обновляет cart.js после добавления товара (событие dharmashop:cart-updated).
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Language\Text;

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$showTotal = (int) $params->get('show_total', 1) === 1;
$hideEmpty = (int) $params->get('hide_empty_badge', 1) === 1;
$cartTag = !empty($cartDisabled) ? 'span' : 'a';
?>
<?php // На чекауте — статичный индикатор без ссылки; на остальных страницах действует режим модуля. ?>
<<?php echo $cartTag; ?> class="d-inline-flex align-items-center gap-2 text-decoration-none dharmaCartModule"<?php if (empty($cartDisabled)) : ?> href="<?php echo $escape($cartUrl); ?>"<?php else : ?> aria-disabled="true"<?php endif; ?> title="<?php echo $escape(Text::_('MOD_DHARMASHOP_CART_TITLE')); ?>"<?php echo $offcanvas ? ' data-bs-toggle="offcanvas" data-bs-target="#dharmashop-minicart-' . (int) $module->id . '" aria-controls="dharmashop-minicart-' . (int) $module->id . '"' : ''; ?>>
    <?php // Число позиций — значок в углу корзины (классы Bootstrap: position-relative / position-absolute / translate-middle). ?>
    <span class="position-relative d-inline-flex">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M0 1.5A.5.5 0 0 1 .5 1H2a.5.5 0 0 1 .485.379L2.89 3H14.5a.5.5 0 0 1 .491.592l-1.5 8A.5.5 0 0 1 13 12H4a.5.5 0 0 1-.491-.408L2.01 3.607 1.61 2H.5a.5.5 0 0 1-.5-.5M3.102 4l1.313 7h8.17l1.313-7zM5 12a2 2 0 1 0 0 4 2 2 0 0 0 0-4m7 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4m-7 1a1 1 0 1 1 0 2 1 1 0 0 1 0-2m7 0a1 1 0 1 1 0 2 1 1 0 0 1 0-2"/></svg>
        <span class="position-absolute top-0 start-100 translate-middle badge text-bg-primary rounded-pill<?php echo $hideEmpty && $count === 0 ? ' d-none' : ''; ?>" data-dharmashop-cart-count<?php echo $hideEmpty ? ' data-hide-empty' : ''; ?>><?php echo (int) $count; ?></span>
    </span>
    <?php if ($showTotal) : ?><span class="d-none d-md-inline small ms-1" data-dharmashop-cart-total><?php echo $escape($total); ?></span><?php endif; ?>
    <span class="visually-hidden"><?php echo $escape(Text::_('MOD_DHARMASHOP_CART_TITLE')); ?></span>
</<?php echo $cartTag; ?>>
<?php if ($offcanvas) : ?>
    <?php // Боковая панель мини-корзины — отдельный файл default_offcanvas.php (переопределяется независимо). ?>
    <?php require ModuleHelper::getLayoutPath('mod_dharmashop_cart', 'default_offcanvas'); ?>
<?php endif; ?>
