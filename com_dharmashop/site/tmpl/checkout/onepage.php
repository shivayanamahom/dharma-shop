<?php
/**
 * Оформление «в один шаг» (C100): слева состав корзины (количество и удаление меняются без перезагрузки, `onepage_cart.php`) и форма
 * заказа (`onepage_main.php`), справа общая боковая панель итогов (`onepage_aside.php`) рядом с обоими блоками. Форма и панель
 * подгружаются заново после каждого изменения корзины (checkout-onepage.js); поля и кнопки панели привязаны к форме атрибутом
 * `form`. Создание заказа то же, что на странице оформления «в два шага».
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
            <div class="row g-4 align-items-start" data-dharmashop-checkout-scope>
                <div class="col-lg-8 d-flex flex-column gap-4 dharmaCheckoutMain">
                    <div data-dharmashop-cart-messages aria-live="polite"></div>
                    <div data-dharmashop-cart-page><?php echo $this->loadTemplate('cart'); ?></div>
                    <div data-dharmashop-onepage-form><?php echo $this->loadTemplate('main'); ?></div>
                </div>
                <div class="col-lg-4" data-dharmashop-onepage-aside><?php echo $this->loadTemplate('aside'); ?></div>
            </div>
        <?php endif; ?>
    </section>
</div>
