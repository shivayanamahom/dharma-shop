<?php
\defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
?>
<section class="card shadow-sm dharmaCheckoutSuccess"><div class="card-body p-4">
    <h2 class="fs-5 mb-3"><?php echo Text::sprintf('COM_DHARMASHOP_CHECKOUT_SUCCESS',$this->escape((string) $this->receipt['number'])); ?></h2>
    <p><?php echo Text::_('COM_DHARMASHOP_CHECKOUT_SUCCESS_TEXT'); ?></p>
    <a class="btn btn-sm btn-outline-secondary" href="<?php echo $this->escape($this->cartUrl); ?>"><?php echo Text::_('COM_DHARMASHOP_CHECKOUT_BACK_TO_CART'); ?></a>
</div></section>
