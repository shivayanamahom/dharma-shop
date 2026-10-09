<?php
/** Оплата заказа на странице заказа: способ, статус оплаты, сведения из снимка и кнопка «Оплатить», если платёж ещё не завершён. */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$payment = (array) ($this->item->payment ?? []);
$title = trim((string) ($payment['title'] ?? $this->item->payment_title ?? ''));
$status = (string) ($this->item->payment_status ?? 'none');
$rows = array_filter((array) ($payment['rows'] ?? []), static fn ($row) => is_array($row) && trim((string) ($row['value'] ?? '')) !== '');
$key = (string) \Joomla\CMS\Factory::getApplication()->getInput()->get('key', '', 'cmd');
$key = preg_match('/^[a-f0-9]{32}$/', $key) === 1 ? '&key=' . $key : '';
$badge = ['paid' => 'text-bg-success', 'pending' => 'text-bg-warning', 'failed' => 'text-bg-danger', 'cancelled' => 'text-bg-secondary'][$status] ?? 'text-bg-light border';
?>
<?php if ($title !== '') : ?>
<section class="card shadow-sm mt-4 dharmaOrderPayment">
    <div class="card-header bg-body-tertiary py-3"><h2 class="fs-6 fw-semibold mb-0"><?php echo Text::_('COM_DHARMASHOP_ORDER_PAYMENT'); ?></h2></div>
    <div class="card-body"><dl class="row gy-2 mb-0">
        <dt class="col-12 col-sm-4 small text-body-secondary fw-normal"><?php echo Text::_('COM_DHARMASHOP_ORDER_PAYMENT_METHOD'); ?></dt>
        <dd class="col-12 col-sm-8 small text-break mb-0"><?php echo $this->escape($title); ?></dd>
        <dt class="col-12 col-sm-4 small text-body-secondary fw-normal"><?php echo Text::_('COM_DHARMASHOP_ORDER_PAYMENT_STATUS'); ?></dt>
        <dd class="col-12 col-sm-8 small mb-0"><span class="badge fw-normal <?php echo $badge; ?>"><?php echo $this->escape(Text::_('COM_DHARMASHOP_PAYMENT_STATUS_' . strtoupper($status))); ?></span></dd>
        <?php foreach ($rows as $row) : ?>
            <dt class="col-12 col-sm-4 small text-body-secondary fw-normal"><?php echo $this->escape((string) ($row['label'] ?? '')); ?></dt>
            <dd class="col-12 col-sm-8 small text-break mb-0"><?php echo nl2br($this->escape((string) $row['value'])); ?></dd>
        <?php endforeach; ?>
    </dl>
    <?php if (in_array($status, ['pending', 'failed', 'cancelled'], true)) : ?>
        <a class="btn btn-primary bg-primary text-white btn-sm mt-3" href="<?php echo $this->escape(Route::_('index.php?option=com_dharmashop&view=payment&id=' . (int) $this->item->id . $key, false)); ?>"><?php echo Text::_('COM_DHARMASHOP_PAYMENT_PAY_NOW'); ?></a>
    <?php endif; ?>
    </div>
</section>
<?php endif; ?>
