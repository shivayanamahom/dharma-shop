<?php
/**
 * Левая нижняя часть оформления «в один шаг» (C100): форма заказа либо сообщение (нужен вход, корзина пуста). Вложенные макеты
 * (контакты, доставка, оплата, согласия) общие с оформлением «в два шага».
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
?>
<?php if ($this->loginRequired) : ?>
    <div class="card shadow-sm"><div class="card-body p-4"><p><?php echo Text::_('COM_DHARMASHOP_CHECKOUT_LOGIN_REQUIRED'); ?></p><a class="btn btn-primary bg-primary text-white" href="<?php echo $this->escape($this->loginUrl); ?>"><?php echo Text::_('COM_DHARMASHOP_CHECKOUT_LOGIN'); ?></a></div></div>
<?php elseif (!$this->lines) : ?>
    <div class="card shadow-sm"><div class="card-body p-4"><p class="mb-3"><?php echo Text::_('COM_DHARMASHOP_CART_EMPTY'); ?></p><a class="btn btn-primary bg-primary text-white" href="<?php echo $this->escape($this->catalogUrl); ?>"><?php echo Text::_('COM_DHARMASHOP_CHECKOUT_ONEPAGE_EMPTY_BACK'); ?></a></div></div>
<?php else : ?>
    <?php echo $this->loadTemplate('form'); ?>
    <form id="dharma-cart-fix" action="<?php echo $this->escape(\Joomla\CMS\Router\Route::_('index.php?option=com_dharmashop&task=cart.removeUnavailable', false)); ?>" method="post" hidden>
        <input type="hidden" name="return" value="checkout">
        <?php echo \Joomla\CMS\HTML\HTMLHelper::_('form.token'); ?>
    </form>
<?php endif; ?>
