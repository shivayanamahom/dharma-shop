<?php
/** Форма заказа в оформлении «в один шаг» (C100): контакты, доставка, оплата, согласия. Боковая панель стоит рядом (`onepage_aside.php`) и привязана к форме атрибутом `form`. */
\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
?>
<form id="dharma-checkout-form" class="dharmaCheckoutForm" action="<?php echo $this->escape(Route::_('index.php?option=com_dharmashop&view=checkout', false)); ?>" method="post" data-dharmashop-checkout-form>
    <div class="d-flex flex-column gap-4 dharmaCheckoutMain">
        <?php echo $this->loadTemplate('contacts'); ?>
        <?php echo $this->loadTemplate('shipping'); ?>
        <?php echo $this->loadTemplate('payment'); ?>
        <?php echo $this->loadTemplate('agreements'); ?>
    </div>
    <?php echo HTMLHelper::_('form.token'); ?>
    <input type="hidden" name="task" value="checkout.submit">
    <div class="visually-hidden" aria-hidden="true" style="position:absolute;left:-10000px"><label>Website <input type="text" name="website" value="" tabindex="-1" autocomplete="off"></label></div>
    <input type="hidden" name="checkout_key" value="<?php echo $this->escape($this->submitKey); ?>">
</form>
<?php echo $this->loadTemplate('processing'); ?>
