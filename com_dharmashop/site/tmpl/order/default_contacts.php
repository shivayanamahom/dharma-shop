<?php
\defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
$fields = array_filter((array) ($this->item->snapshot['fields'] ?? []), static fn ($field) => is_array($field) && trim((string) ($field['value'] ?? '')) !== '');
?>
<?php if ($fields) : ?>
<section class="card shadow-sm dharmaOrderContacts">
    <div class="card-header bg-body-tertiary py-3"><h2 class="fs-6 fw-semibold mb-0"><?php echo Text::_('COM_DHARMASHOP_ORDER_RECIPIENT'); ?></h2></div>
    <div class="card-body"><dl class="row gy-2 mb-0">
        <?php foreach ($fields as $field) : ?>
            <dt class="col-12 col-sm-4 small text-body-secondary fw-normal"><?php echo $this->escape((string) ($field['label'] ?? $field['name'] ?? '')); ?></dt>
            <dd class="col-12 col-sm-8 small text-break mb-0"><?php echo nl2br($this->escape((string) $field['value'])); ?></dd>
        <?php endforeach; ?>
    </dl></div>
</section>
<?php endif; ?>
