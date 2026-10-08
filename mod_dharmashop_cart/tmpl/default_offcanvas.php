<?php
/**
 * Боковая панель мини-корзины (offcanvas Bootstrap справа). Содержимое подгружает mini-cart.js фрагментом `cart/mini`
 * (tmpl/cart/mini.php компонента); эту оболочку (заголовок, кнопка закрытия) можно переопределить отдельно.
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$panelId = 'dharmashop-minicart-' . (int) $module->id;
?>
<div class="offcanvas offcanvas-end" tabindex="-1" id="<?php echo $panelId; ?>" aria-labelledby="<?php echo $panelId; ?>-title" data-dharmashop-minicart data-open-on-add="<?php echo $openOnAdd ? '1' : '0'; ?>">
    <div class="offcanvas-header">
        <h2 class="offcanvas-title h5" id="<?php echo $panelId; ?>-title"><?php echo Text::_('MOD_DHARMASHOP_CART_TITLE'); ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
    </div>
    <div class="offcanvas-body" data-dharmashop-minicart-body>
        <div class="text-center py-5" role="status"><span class="spinner-border" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('MOD_DHARMASHOP_CART_LOADING'); ?></span></div>
    </div>
</div>
