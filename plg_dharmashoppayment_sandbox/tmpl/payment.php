<?php
/**
 * Страница тестовой оплаты (имитация шлюза). Переопределение: templates/<шаблон>/html/plg_dharmashoppayment_sandbox/payment.php
 *
 * @var  array  $displayData  method (Method), order (OrderPayment: number, total, currency), urls (Urls), info, status, message,
 *                            amount_text (сумма к оплате готовой строкой), links (результат => подписанный адрес обратного вызова: paid, failed, cancelled)
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$order = $displayData['order'];
$links = (array) $displayData['links'];
$esc = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<div class="card shadow-sm dharmaSandboxPay">
    <div class="card-body p-4">
        <span class="badge text-bg-warning mb-3"><?php echo Text::_('PLG_DHARMASHOPPAYMENT_SANDBOX_BADGE'); ?></span>
        <h2 class="fs-5 mb-1"><?php echo Text::_('PLG_DHARMASHOPPAYMENT_SANDBOX_PAGE_TITLE'); ?></h2>
        <p class="text-body-secondary small mb-3"><?php echo Text::_('PLG_DHARMASHOPPAYMENT_SANDBOX_PAGE_TEXT'); ?></p>
        <dl class="bg-body-tertiary rounded p-3 mb-4">
            <dt class="small text-body-secondary fw-normal"><?php echo Text::_('COM_DHARMASHOP_PAYMENT_AMOUNT'); ?></dt>
            <dd class="fs-4 fw-semibold mb-0"><?php echo $esc($displayData['amount_text'] ?? ''); ?></dd>
        </dl>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-success" href="<?php echo $esc($links['paid'] ?? '#'); ?>"><?php echo Text::_('PLG_DHARMASHOPPAYMENT_SANDBOX_PAY'); ?></a>
            <a class="btn btn-outline-danger" href="<?php echo $esc($links['failed'] ?? '#'); ?>"><?php echo Text::_('PLG_DHARMASHOPPAYMENT_SANDBOX_FAIL'); ?></a>
            <a class="btn btn-outline-secondary" href="<?php echo $esc($links['cancelled'] ?? '#'); ?>"><?php echo Text::_('PLG_DHARMASHOPPAYMENT_SANDBOX_CANCEL'); ?></a>
        </div>
    </div>
</div>
