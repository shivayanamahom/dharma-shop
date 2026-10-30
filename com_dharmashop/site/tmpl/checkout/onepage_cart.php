<?php
/**
 * Состав корзины в оформлении «в один шаг» (C100): те же карточки позиций и действия, что на странице корзины. Подгружается и
 * целиком заменяется скриптом cart-page.js (фрагмент `part=cart`); без JavaScript работают обычные формы. Данные готовит вид.
 */
\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

if (!$this->lines) {
    return;
}

$update = Route::_('index.php?option=com_dharmashop&task=cart.update', false);
$remove = Route::_('index.php?option=com_dharmashop&task=cart.remove', false);
$clear = Route::_('index.php?option=com_dharmashop&task=cart.clear', false);
?>
<form action="<?php echo $this->escape($update); ?>" method="post" class="dharmaCartForm fs-6">
    <h2 class="fs-5 mb-3"><?php echo Text::_('COM_DHARMASHOP_CART_TITLE'); ?></h2>
    <div class="dharmaCartList">
        <?php echo (new \Joomla\CMS\Layout\FileLayout('components.dharmashop.cart.lines', null, ['component' => 'none', 'client' => 0]))->render(['lines' => $this->lines, 'money' => $this->money, 'remove' => $remove]); ?>
        <div class="d-flex flex-wrap gap-2">
            <?php // Без JavaScript количество меняется этой кнопкой; с JavaScript пересчёт происходит сразу, и кнопки нет. ?>
            <noscript><button type="submit" class="btn btn-sm btn-outline-primary"><?php echo Text::_('COM_DHARMASHOP_CART_UPDATE'); ?></button></noscript>
            <button type="submit" class="btn btn-sm btn-outline-secondary" formaction="<?php echo $this->escape($clear); ?>" data-dharmashop-cartpage-clear><?php echo Text::_('COM_DHARMASHOP_CART_CLEAR'); ?></button>
        </div>
    </div>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
