<?php
\defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
$order = $this->item;
$money = fn ($amount): string => $this->escape(number_format((float) $amount,2,',',' ') . ' ' . $order->currency);
$qty = static fn ($value): string => rtrim(rtrim(number_format((float) $value,3,',',' '),'0'),',');
?>
<div class="mb-3"><a class="btn btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_dharmashop&view=orders'); ?>"><?php echo Text::_('COM_DHARMASHOP_ORDER_BACK'); ?></a></div>
<div class="row g-4">
    <div class="col-lg-8">
        <section class="card mb-4"><div class="card-header"><h2 class="fs-6 mb-0"><?php echo Text::_('COM_DHARMASHOP_ORDER_RECIPIENT'); ?></h2></div><div class="card-body"><dl class="row mb-0">
            <?php foreach (($order->snapshot['fields'] ?? []) as $field) : ?><dt class="col-md-4 text-break"><?php echo $this->escape((string) $field['label']); ?></dt><dd class="col-md-8 text-break"><?php echo nl2br($this->escape((string) $field['value'])); ?></dd><?php endforeach; ?>
            <?php if (empty($order->snapshot['fields'])) : ?><?php foreach (['COM_DHARMASHOP_CUSTOMER_NAME'=>$order->contact_name,'JGLOBAL_EMAIL'=>$order->contact_email,'COM_DHARMASHOP_CUSTOMER_PHONE'=>$order->contact_phone] as $label=>$value) : ?><dt class="col-md-4"><?php echo Text::_($label); ?></dt><dd class="col-md-8"><?php echo $this->escape($value); ?></dd><?php endforeach; ?><?php endif; ?>
        </dl></div></section>
        <?php if ($order->shipping_title !== '') : ?><section class="card mb-4"><div class="card-header"><h2 class="fs-6 mb-0"><?php echo Text::_('COM_DHARMASHOP_ORDER_SHIPPING'); ?></h2></div><div class="card-body"><dl class="row mb-0">
            <dt class="col-md-4"><?php echo Text::_('COM_DHARMASHOP_ORDER_SHIPPING_METHOD'); ?></dt><dd class="col-md-8"><?php echo $this->escape($order->shipping_title); ?><?php if (!empty($order->shipping['term'])) : ?> <span class="text-muted">· <?php echo $this->escape((string) $order->shipping['term']); ?></span><?php endif; ?></dd>
            <?php foreach ((array) ($order->shipping['rows'] ?? []) as $row) : ?><dt class="col-md-4"><?php echo $this->escape((string) ($row['label'] ?? '')); ?></dt><dd class="col-md-8"><?php echo nl2br($this->escape((string) ($row['value'] ?? ''))); ?></dd><?php endforeach; ?>
        </dl></div></section><?php endif; ?>
        <section class="card mb-4"><div class="card-header"><h2 class="fs-6 mb-0"><?php echo Text::_('COM_DHARMASHOP_ORDER_ITEMS'); ?></h2></div><div class="card-body"><div class="table-responsive"><table class="table align-middle"><thead><tr><th><?php echo Text::_('JGLOBAL_TITLE'); ?></th><th><?php echo Text::_('COM_DHARMASHOP_ORDER_QTY'); ?></th><th><?php echo Text::_('COM_DHARMASHOP_HEADING_PRICE'); ?></th><th><?php echo Text::_('COM_DHARMASHOP_ORDER_TOTAL'); ?></th></tr></thead><tbody>
            <?php foreach ($order->items as $item) : $params = json_decode((string) $item->params,true) ?: []; ?><tr><td><div class="fw-semibold"><?php echo $this->escape($item->title); ?></div><div class="small text-body-secondary"><?php echo $this->escape($item->sku); ?></div><?php if (!empty($params['variant_title'])) : ?><div class="small"><?php echo $this->escape($params['variant_title']); ?></div><?php endif; ?></td><td class="text-nowrap"><?php echo $this->escape($qty($item->qty) . ' ' . $item->unit); ?></td><td class="text-nowrap"><?php echo $item->price === null ? '—' : $money($item->price); ?></td><td class="text-nowrap"><?php echo $item->total === null ? '—' : $money($item->total); ?></td></tr><?php endforeach; ?>
        </tbody></table></div></div></section>
        <?php if (!empty($order->snapshot['consents'])) : ?><section class="card mb-4"><div class="card-header"><h2 class="fs-6 mb-0"><?php echo Text::_('COM_DHARMASHOP_ORDER_CONSENTS'); ?></h2></div><div class="card-body d-flex flex-column gap-3">
            <?php foreach ($order->snapshot['consents'] as $consent) : ?><div><div class="small fw-semibold"><?php echo Text::_(!empty($consent['accepted']) ? 'COM_DHARMASHOP_ORDER_CONSENT_YES' : 'COM_DHARMASHOP_ORDER_CONSENT_NO'); ?><?php if (!empty($consent['accepted_at'])) : ?> · <?php echo HTMLHelper::_('date',$consent['accepted_at'],Text::_('DATE_FORMAT_LC6')); ?><?php endif; ?></div><div class="small text-break"><?php echo nl2br($this->escape((string) $consent['text'])); ?></div><?php if (!empty($consent['url'])) : ?><div class="small text-body-secondary text-break"><?php echo $this->escape((string) $consent['url']); ?></div><?php endif; ?></div><?php endforeach; ?>
        </div></section><?php endif; ?>
        <section class="card"><div class="card-header"><h2 class="fs-6 mb-0"><?php echo Text::_('COM_DHARMASHOP_ORDER_HISTORY'); ?></h2></div><div class="card-body"><ul class="list-unstyled mb-0 d-flex flex-column gap-3">
            <?php foreach ($order->history as $entry) : ?><li><div class="small text-body-secondary"><?php echo HTMLHelper::_('date',$entry->created,Text::_('DATE_FORMAT_LC6')); ?> · <?php echo $this->escape($entry->status_title); ?><?php if ($entry->author) : ?> · <?php echo $this->escape($entry->author); ?><?php endif; ?></div><div><?php echo $this->escape($entry->comment); ?></div>
                <?php if ($entry->delivery) : ?>
                    <div class="small text-body-secondary mt-2"><?php echo Text::sprintf('COM_DHARMASHOP_NOTIFY_DELIVERY_COUNTS', (int) $entry->delivery->sent_count, (int) $entry->delivery->failed_count, (int) $entry->delivery->pending_count); ?></div>
                    <?php if ((int) $entry->delivery->pending_count > 0) : ?><div class="small text-warning"><?php echo Text::_('COM_DHARMASHOP_NOTIFY_PENDING_DESC'); ?></div><?php endif; ?>
                    <?php if ((int) $entry->delivery->failed_count > 0) : ?>
                        <form action="<?php echo Route::_('index.php?option=com_dharmashop&task=order.retryNotifications'); ?>" method="post" class="mt-2">
                            <input type="hidden" name="id" value="<?php echo (int) $order->id; ?>">
                            <input type="hidden" name="history_id" value="<?php echo (int) $entry->id; ?>">
                            <button class="btn btn-sm btn-outline-secondary" type="submit"><?php echo Text::_('COM_DHARMASHOP_NOTIFY_RETRY'); ?></button>
                            <?php echo HTMLHelper::_('form.token'); ?>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </li><?php endforeach; ?>
        </ul></div></section>
    </div>
    <div class="col-lg-4">
        <section class="card mb-4"><div class="card-header"><h2 class="fs-6 mb-0"><?php echo Text::_('COM_DHARMASHOP_ORDER_CHANGE_STATUS'); ?></h2></div><div class="card-body">
            <form action="<?php echo Route::_('index.php?option=com_dharmashop&task=order.changeStatus'); ?>" method="post">
                <?php echo $this->statusForm->renderField('status_id'); ?>
                <input type="hidden" name="id" value="<?php echo (int) $order->id; ?>">
                <input type="hidden" name="previous_status_id" value="<?php echo (int) $order->status_id; ?>">
                <button type="submit" class="btn btn-primary btn-sm"><?php echo Text::_('COM_DHARMASHOP_ORDER_CHANGE_STATUS'); ?></button>
                <?php echo HTMLHelper::_('form.token'); ?>
            </form>
        </div></section>
        <section class="card"><div class="card-header"><h2 class="fs-6 mb-0"><?php echo Text::sprintf('COM_DHARMASHOP_ORDER_TITLE',$this->escape($order->number)); ?></h2></div><div class="card-body"><dl class="row">
        <dt class="col-5"><?php echo Text::_('JSTATUS'); ?></dt><dd class="col-7"><?php echo $this->escape($order->status_title); ?></dd><dt class="col-5"><?php echo Text::_('JDATE'); ?></dt><dd class="col-7"><?php echo HTMLHelper::_('date',$order->created,Text::_('DATE_FORMAT_LC6')); ?></dd>
        <dt class="col-5"><?php echo Text::_('COM_DHARMASHOP_CUSTOMER_TITLE'); ?></dt><dd class="col-7"><?php if ($order->customer_id && $this->getCurrentUser()->authorise('dharmashop.customers.view','com_dharmashop')) : ?><a href="<?php echo Route::_('index.php?option=com_dharmashop&' . ($this->getCurrentUser()->authorise('core.edit','com_dharmashop') ? 'task=customer.edit' : 'view=customer&layout=edit') . '&id=' . (int) $order->customer_id); ?>">#<?php echo (int) $order->customer_id; ?></a><?php else : ?>#<?php echo (int) $order->customer_id; ?><?php endif; ?></dd>
        <dt class="col-5"><?php echo Text::_('COM_DHARMASHOP_CUSTOMER_ACCOUNT'); ?></dt><dd class="col-7"><?php echo $order->user_id ? '#' . (int) $order->user_id : Text::_('COM_DHARMASHOP_CUSTOMER_GUEST'); ?></dd>
    </dl>
        <?php // Доступ покупателя: секретный ключ заказа и ссылка для гостя (то же, что уходит в письме); заказ вошедшего пользователя открывается после его входа. ?>
        <div class="border-top pt-3 mb-3 small">
            <div class="text-body-secondary mb-1"><?php echo Text::_('COM_DHARMASHOP_ORDER_SECRET'); ?></div>
            <input type="text" class="form-control form-control-sm font-monospace mb-2" readonly value="<?php echo $this->escape((string) $order->secret); ?>">
            <?php if (!$order->user_id) : ?>
                <?php $guestUrl = rtrim(\Joomla\CMS\Uri\Uri::root(), '/') . '/index.php?option=com_dharmashop&view=order&id=' . (int) $order->id . '&key=' . rawurlencode((string) $order->secret); ?>
                <a class="btn btn-sm btn-outline-secondary" href="<?php echo $this->escape($guestUrl); ?>" target="_blank" rel="noopener noreferrer"><?php echo Text::_('COM_DHARMASHOP_ORDER_OPEN_ON_SITE'); ?></a>
            <?php elseif ($this->getCurrentUser()->authorise('core.manage', 'com_users')) : ?>
                <a class="btn btn-sm btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_users&task=user.edit&id=' . (int) $order->user_id); ?>"><?php echo Text::_('COM_DHARMASHOP_CUSTOMER_OPEN_USER'); ?></a>
            <?php endif; ?>
        </div>
    <div class="border-top pt-3 d-flex flex-column gap-2"><div class="d-flex justify-content-between gap-3"><span><?php echo Text::_('COM_DHARMASHOP_ORDER_SUBTOTAL'); ?></span><span><?php echo $money($order->subtotal); ?></span></div>
        <?php foreach ($order->adjustments as $adjustment) : ?><div class="d-flex justify-content-between gap-3"><span><?php echo $this->escape($adjustment->title); ?></span><span><?php echo $money($adjustment->amount); ?></span></div><?php endforeach; ?>
        <div class="d-flex justify-content-between gap-3 fw-semibold border-top pt-2"><span><?php echo Text::_('COM_DHARMASHOP_ORDER_TOTAL'); ?></span><span><?php echo $money($order->total); ?></span></div>
    </div></div></section></div>
</div>
