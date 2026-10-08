<?php
/**
 * Страница корзины: заголовок и контейнер, содержимое — `default_content.php` (его же отдаёт фрагмент `layout=page&format=raw`).
 * Количество, удаление и очистка выполняются AJAX (media/com_dharmashop/js/cart-page.js, кнопки «Пересчитать» нет) и сразу видны в модуле
 * корзины; без JavaScript остаются обычные формы. Данные готовит вид (`$this->lines`, `$this->totals`), SQL здесь нет.
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
?>
<div id="dharma_shop" class="dharmaCart fs-6">
<section class="py-4">
    <h1 class="fs-4 mb-4"><?php echo Text::_('COM_DHARMASHOP_CART_TITLE'); ?></h1>
    <div data-dharmashop-cart-messages aria-live="polite"></div>
    <?php if ($this->readOnly) : ?>
        <?php // Корзина другого посетителя (ссылка из админки): кнопки и поля недоступны (fieldset disabled), скрипты не подключены. ?>
        <div class="alert alert-info"><?php echo Text::_('COM_DHARMASHOP_CARTS_READONLY'); ?></div>
        <fieldset disabled class="border-0 p-0 m-0" data-dharmashop-cart-page>
            <?php echo $this->loadTemplate('content'); ?>
        </fieldset>
    <?php else : ?>
        <div data-dharmashop-cart-page>
            <?php echo $this->loadTemplate('content'); ?>
        </div>
    <?php endif; ?>
</section>
</div>
