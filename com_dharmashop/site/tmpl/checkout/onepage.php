<?php
/**
 * Оформление «в один шаг» (C100): вверху состав корзины (количество и удаление меняются без перезагрузки, `onepage_cart.php`),
 * ниже форма заказа с общей боковой панелью итогов (`onepage_content.php`). Нижняя часть подгружается заново после каждого
 * изменения корзины (checkout-onepage.js). Создание заказа то же, что на странице оформления «в два шага».
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
?>
<div id="dharma_shop" class="dharmaCheckout dharmaCheckoutOnepage fs-6">
    <section class="py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <h1 class="fs-4 mb-0"><?php echo Text::_('COM_DHARMASHOP_CHECKOUT_TITLE'); ?></h1>
        </div>
        <?php if ($this->receipt) : ?>
            <?php echo $this->loadTemplate('success'); ?>
        <?php else : ?>
            <div data-dharmashop-cart-messages aria-live="polite"></div>
            <div class="mb-4" data-dharmashop-cart-page><?php echo $this->loadTemplate('cart'); ?></div>
            <div data-dharmashop-onepage-form><?php echo $this->loadTemplate('content'); ?></div>
        <?php endif; ?>
    </section>
</div>
