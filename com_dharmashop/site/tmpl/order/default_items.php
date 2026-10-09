<?php
\defined('_JEXEC') or die;
use Dharma\Component\DharmaShop\Site\Support\OrderDisplay;
use Joomla\CMS\Language\Text;
$money = $this->details->money;
?>
<section class="card shadow-sm dharmaOrderItems mb-4">
    <div class="card-header bg-body-tertiary py-3"><h2 class="fs-6 fw-semibold mb-0"><?php echo Text::_('COM_DHARMASHOP_ORDER_CONTENTS'); ?></h2></div>
    <ul class="list-group list-group-flush">
        <?php foreach ($this->item->items as $line) : $params = json_decode((string) $line->params, true) ?: []; ?>
            <li class="list-group-item p-3">
                <div class="row g-2">
                    <div class="col-12 col-sm-7">
                        <div class="fw-semibold text-break"><?php echo $this->escape($line->title); ?></div>
                        <?php if (!empty($params['variant_title'])) : ?><div class="small text-body-secondary"><?php echo $this->escape($params['variant_title']); ?></div><?php endif; ?>
                        <?php if ($line->sku !== '') : ?><div class="small text-body-secondary"><?php echo Text::_('COM_DHARMASHOP_ORDER_SKU'); ?>: <?php echo $this->escape($line->sku); ?></div><?php endif; ?>
                    </div>
                    <div class="col-12 col-sm-5 text-sm-end">
                        <div class="fw-semibold"><?php echo $this->escape($line->total === null ? $money->zeroText() : $money->price($line->total, !empty($params['zero_free']))); ?></div>
                        <div class="small text-body-secondary"><?php echo $this->escape(OrderDisplay::quantity((string) $line->qty) . ' ' . $line->unit); ?> × <?php echo $this->escape($line->price === null ? $money->zeroText() : $money->price($line->price, !empty($params['zero_free']))); ?></div>
                    </div>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
