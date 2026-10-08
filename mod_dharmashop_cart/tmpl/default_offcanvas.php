<?php
/**
 * Боковая панель мини-корзины (offcanvas Bootstrap справа). Содержимое подгружает mini-cart.js фрагментом `cart/mini`
 * (tmpl/cart/mini.php компонента); эту оболочку (заголовок, кнопка закрытия) можно переопределить отдельно.
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$panelId = 'dharmashop-minicart-' . (int) $module->id;
$styled = (int) $params->get('panel_styles', 1) === 1;
?>
<div class="offcanvas offcanvas-end dharmaMiniCartPanel" tabindex="-1" id="<?php echo $panelId; ?>" aria-labelledby="<?php echo $panelId; ?>-title" data-dharmashop-minicart data-open-on-add="<?php echo $openOnAdd ? '1' : '0'; ?>">
    <div class="offcanvas-header">
        <h2 class="offcanvas-title h5" id="<?php echo $panelId; ?>-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M8 1a2.5 2.5 0 0 1 2.5 2.5V4h-5v-.5A2.5 2.5 0 0 1 8 1m3.5 3v-.5a3.5 3.5 0 1 0-7 0V4H1v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V4zM2 5h12v9a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1z"/></svg>
            <?php echo Text::_('MOD_DHARMASHOP_CART_TITLE'); ?>
        </h2>
        <?php if ($styled) : ?>
            <button type="button" class="dharmaMiniCartClose" data-bs-dismiss="offcanvas" aria-label="<?php echo Text::_('JCLOSE'); ?>"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z"/></svg></button>
        <?php else : ?>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
        <?php endif; ?>
    </div>
    <div class="offcanvas-body" data-dharmashop-minicart-body>
        <div class="text-center py-5" role="status"><span class="spinner-border" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('MOD_DHARMASHOP_CART_LOADING'); ?></span></div>
    </div>
</div>
