<?php

/**
 * Страница оплаты заказа. Страница оплаты способа (реквизиты, кнопки, форма) — макет плагина типа, внутри контейнера магазина.
 *
 * @var  \Dharma\Component\DharmaShop\Site\View\Payment\HtmlView  $this
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
?>
<div id="dharma_shop" class="dharmaPayment fs-6">
    <section class="py-4">
        <h1 class="fs-4 mb-3"><?php echo Text::sprintf('COM_DHARMASHOP_PAYMENT_PAGE_TITLE', $this->escape($this->item->number)); ?></h1>
        <?php echo $this->html; ?>
        <a class="btn btn-sm btn-outline-secondary mt-3" href="<?php echo $this->escape($this->orderUrl); ?>"><?php echo Text::_('COM_DHARMASHOP_ORDER_OPEN'); ?></a>
    </section>
</div>
