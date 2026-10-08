<?php
/** Способ доставки заказа из снимка (название, цена, срок и сведения, которые дал плагин типа); плагин для показа не нужен. */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$shipping = (array) ($this->item->shipping ?? []);
$title = trim((string) ($shipping['title'] ?? $this->item->shipping_title ?? ''));
$rows = array_filter((array) ($shipping['rows'] ?? []), static fn ($row) => is_array($row) && trim((string) ($row['value'] ?? '')) !== '');
?>
<?php if ($title !== '') : ?>
<section class="card shadow-sm mt-4 dharmaOrderShipping">
    <div class="card-header bg-body-tertiary py-3"><h2 class="fs-6 fw-semibold mb-0"><?php echo Text::_('COM_DHARMASHOP_ORDER_SHIPPING'); ?></h2></div>
    <div class="card-body"><dl class="row gy-2 mb-0">
        <dt class="col-12 col-sm-4 small text-body-secondary fw-normal"><?php echo Text::_('COM_DHARMASHOP_ORDER_SHIPPING_METHOD'); ?></dt>
        <dd class="col-12 col-sm-8 small text-break mb-0"><?php echo $this->escape($title); ?><?php if (!empty($shipping['term'])) : ?> <span class="text-body-secondary">· <?php echo $this->escape((string) $shipping['term']); ?></span><?php endif; ?></dd>
        <?php foreach ($rows as $row) : ?>
            <dt class="col-12 col-sm-4 small text-body-secondary fw-normal"><?php echo $this->escape((string) ($row['label'] ?? '')); ?></dt>
            <dd class="col-12 col-sm-8 small text-break mb-0"><?php echo nl2br($this->escape((string) $row['value'])); ?></dd>
        <?php endforeach; ?>
    </dl></div>
</section>
<?php endif; ?>
