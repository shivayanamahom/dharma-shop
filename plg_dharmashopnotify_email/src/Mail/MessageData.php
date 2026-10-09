<?php
declare(strict_types=1);
namespace Dharma\Plugin\DharmaShopNotify\Email\Mail;

use Dharma\Component\DharmaShop\Administrator\Notification\OrderNotification;
use Dharma\Component\DharmaShop\Administrator\Pricing\MoneyFormatter;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Mail\MailHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;
\defined('_JEXEC') or die;

use Dharma\Component\DharmaShop\Administrator\Image\ImageUrl;

/** Снимок заказа с необязательной обложкой/ссылкой; без HTML из данных покупателя. */
final class MessageData
{
    public function build(OrderNotification $message, Registry $settings, string $audience, string $siteName, Language $language): array
    {
        $order = $message->order;
        $params = clone $settings;
        if ($order['currency'] !== $params->get('currency_code', 'RUB')) $params->set('currency_symbol', $order['currency']);
        $money = new MoneyFormatter($params);
        $root = rtrim(Uri::root(), '/');
        $items = []; $plainItems = []; $unknown = false;
        foreach ($message->items as $item) {
            $variant = json_decode((string) $item['params'], true) ?: [];
            $title = $item['title'] . (!empty($variant['variant_title']) ? ' — ' . $variant['variant_title'] : '');
            $qty = rtrim(rtrim(number_format((float) $item['qty'], 3, ',', ' '), '0'), ',') . ' ' . $item['unit'];
            $free = !empty($variant['zero_free']);
            $price = $item['price'] === null ? $money->zeroText() : $money->price($item['price'], $free);
            $total = $item['total'] === null ? $money->zeroText() : $money->price($item['total'], $free);
            $productId = (int) ($item['available_product_id'] ?? 0);
            $url = $productId > 0 ? $root . '/index.php?option=com_dharmashop&view=product&id=' . $productId : '';
            $image = $productId > 0 ? ImageUrl::resolve((string) ($item['cover'] ?? ''), 'email.product') : '';
            $items[] = ['title'=>$title, 'sku'=>$item['sku'], 'qty'=>$qty, 'price'=>$price, 'total'=>$total, 'url'=>$url, 'image'=>$image];
            $plainItems[] = $title . ($item['sku'] !== '' ? ' [' . $item['sku'] . ']' : '') . "\n" . $qty . ' × ' . $price . ' = ' . $total;
            if ($item['price'] === null || ($money->replacesZero() && (float) $item['price'] === 0.0 && !$free)) $unknown = true;
        }
        $adjustments = [];
        foreach ($message->adjustments as $adjustment) {
            $adjustments[] = ['title'=>$adjustment['title'], 'amount'=>$money->format($adjustment['amount'])];
            $plainItems[] = $adjustment['title'] . ': ' . $money->format($adjustment['amount']);
        }
        $total = $unknown ? ((float) $order['total'] > 0 ? $money->format($order['total']) . ' + ' : '') . $money->zeroText() : $money->format($order['total']);
        $contacts = []; $plainContacts = [];
        foreach (($message->contacts['fields'] ?? []) as $field) {
            $value = trim((string) ($field['value'] ?? ''));
            if ($value === '') continue;
            $contacts[] = ['label'=>(string) $field['label'], 'value'=>$value];
            $plainContacts[] = $field['label'] . ': ' . $value;
        }
        $link = $audience === 'admin' ? $root . '/administrator/index.php?option=com_dharmashop&view=order&id=' . (int) $order['id'] : '';
        if ($audience === 'customer' && (int) $settings->get('notify_customer_link', 1) === 1) {
            // Заказ вошедшего пользователя открывается после входа; гостевой — по секретному ключу из письма (без сессии и срока).
            $link = $root . '/index.php?option=com_dharmashop&view=order&id=' . (int) $order['id']
                . (empty($order['user_id']) && preg_match('/^[a-f0-9]{32}$/', (string) ($order['secret'] ?? '')) ? '&key=' . $order['secret'] : '');
        }
        $shopName = trim((string) $settings->get('shop_name', '')) ?: $siteName;
        $shopEmail = trim((string) $settings->get('shop_email', ''));
        if (!MailHelper::isEmailAddress($shopEmail)) $shopEmail = '';
        $shopPhone = trim((string) $settings->get('shop_phone', ''));
        $shopAddress = trim((string) $settings->get('shop_address', ''));
        $logo = ImageUrl::resolve((string) $settings->get('shop_logo', ''), 'email.logo');
        $showItems = $audience === 'admin' || (int) $settings->get('notify_customer_items', 1) === 1;
        $showContacts = $audience === 'admin' || (int) $settings->get('notify_customer_contacts', 1) === 1;
        $labels = [];
        foreach (['ORDER','DATE','STATUS','ITEMS','PRODUCT','AMOUNT','CONTACTS','SHIPPING','PAYMENT','OPEN_ORDER','CONSENTS','ACCEPTED','NOT_ACCEPTED','ACCEPTED_AT'] as $key) $labels[$key] = $language->_('PLG_DHARMASHOPNOTIFY_EMAIL_LABEL_' . $key);
        $consents = []; $plainConsents = [];
        foreach (($message->contacts['consents'] ?? []) as $consent) {
            if (!array_key_exists('accepted', $consent)) continue;
            $text = trim(strip_tags((string) ($consent['text'] ?? '') . ' ' . (string) ($consent['link_label'] ?? '')));
            if ($text === '') $text = (string) ($consent['name'] ?? '');
            $accepted = (bool) ($consent['accepted'] ?? false);
            $date = $accepted && !empty($consent['accepted_at']) ? HTMLHelper::_('date', $consent['accepted_at'], 'd.m.Y H:i') : '';
            $url = trim((string) ($consent['url'] ?? ''));
            if (str_starts_with($url, '/') && !str_starts_with($url, '//')) $url = $root . $url;
            if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http','https'], true)) $url = '';
            $state = $accepted ? $labels['ACCEPTED'] : $labels['NOT_ACCEPTED'];
            $consents[] = ['text'=>$text, 'state'=>$state, 'date'=>$date, 'url'=>$url];
            $plainConsents[] = $text . ': ' . $state . ($date !== '' ? ' (' . $date . ')' : '') . ($url !== '' ? "\n" . $url : '');
        }
        // Доставка из снимка заказа: способ с ценой, срок и сведения плагина типа (адрес, контакты пункта, введённые покупателем поля).
        $shippingRows = [];
        $snapshot = json_decode((string) ($order['shipping_data'] ?? ''), true) ?: [];
        $shippingTitle = trim((string) ($snapshot['title'] ?? $order['shipping_title'] ?? ''));
        if ($shippingTitle !== '') {
            $shippingPrice = array_key_exists('price', $snapshot) ? (!empty($snapshot['free']) || (float) $snapshot['price'] <= 0 ? $language->_('PLG_DHARMASHOPNOTIFY_EMAIL_SHIPPING_FREE') : $money->format((string) $snapshot['price'])) : '';
            $shippingRows[] = ['label'=>$language->_('PLG_DHARMASHOPNOTIFY_EMAIL_SHIPPING_METHOD'), 'value'=>$shippingTitle . ($shippingPrice !== '' ? ' — ' . $shippingPrice : '')];
            if (trim((string) ($snapshot['term'] ?? '')) !== '') $shippingRows[] = ['label'=>$language->_('PLG_DHARMASHOPNOTIFY_EMAIL_SHIPPING_TERM'), 'value'=>trim((string) $snapshot['term'])];
            foreach ((array) ($snapshot['rows'] ?? []) as $row) {
                $label = trim((string) ($row['label'] ?? '')); $value = trim((string) ($row['value'] ?? ''));
                if ($label !== '' && $value !== '') $shippingRows[] = ['label'=>$label, 'value'=>$value];
            }
        }
        // Оплата из снимка заказа: способ, статус оплаты (если платёж идёт на сайте) и сведения типа (инструкция, реквизиты).
        $paymentRows = [];
        $paymentSnapshot = json_decode((string) ($order['payment_data'] ?? ''), true) ?: [];
        $paymentTitle = trim((string) ($paymentSnapshot['title'] ?? $order['payment_title'] ?? ''));
        if ($paymentTitle !== '') {
            $paymentRows[] = ['label'=>$language->_('PLG_DHARMASHOPNOTIFY_EMAIL_PAYMENT_METHOD'), 'value'=>$paymentTitle];
            $paymentStatus = (string) ($order['payment_status'] ?? 'none');
            if ($paymentStatus !== 'none' && $paymentStatus !== '') $paymentRows[] = ['label'=>$language->_('PLG_DHARMASHOPNOTIFY_EMAIL_PAYMENT_STATUS'), 'value'=>$language->_('PLG_DHARMASHOPNOTIFY_EMAIL_PAYMENT_STATUS_' . strtoupper($paymentStatus))];
            foreach ((array) ($paymentSnapshot['rows'] ?? []) as $row) {
                $label = trim((string) ($row['label'] ?? '')); $value = trim((string) ($row['value'] ?? ''));
                if ($label !== '' && $value !== '') $paymentRows[] = ['label'=>$label, 'value'=>$value];
            }
        }
        $plain = [
            'SITENAME'=>$shopName, 'SHOP_NAME'=>$shopName, 'SHOP_EMAIL'=>$shopEmail, 'SHOP_PHONE'=>$shopPhone, 'SHOP_ADDRESS'=>$shopAddress,
            'SHOP_URL'=>$root . '/', 'SHOP_LOGO'=>$logo, 'SHOP_CONTACTS'=>implode("\n", array_filter([$shopPhone, $shopEmail, $shopAddress, $root . '/'])),
            'ORDER_NUMBER'=>$order['number'], 'ORDER_DATE'=>HTMLHelper::_('date', $order['created'], 'd.m.Y H:i'),
            'STATUS'=>(string) $order['status_title'], 'STATUS_MESSAGE'=>$message->reason === 'status' ? (string) $order['status_message'] : '',
            'TOTAL'=>$total, 'ITEMS'=>$showItems ? implode("\n\n", $plainItems) : '', 'CONTACTS'=>$showContacts ? implode("\n", $plainContacts) : '', 'ORDER_LINK'=>$link,
            'SHIPPING_TITLE'=>trim((string) ($order['shipping_title'] ?? '')), 'PAYMENT_TITLE'=>trim((string) ($order['payment_title'] ?? '')),
            'CONSENTS'=>$plainConsents ? $labels['CONSENTS'] . ":\n" . implode("\n\n", $plainConsents) : '',
        ];
        $plain['ORDER_DETAILS'] = $labels['ORDER'] . ' №' . $plain['ORDER_NUMBER'] . "\n" . $labels['DATE'] . ': ' . $plain['ORDER_DATE'] . "\n" . $labels['STATUS'] . ': ' . $plain['STATUS'];
        $plain['SHIPPING'] = $plain['SHIPPING_TITLE'] !== '' ? $labels['SHIPPING'] . ': ' . $plain['SHIPPING_TITLE'] : '';
        if (count($shippingRows) > 1 || ($shippingRows && $plain['SHIPPING_TITLE'] === '')) {
            $plain['SHIPPING'] = $labels['SHIPPING'] . ":\n" . implode("\n", array_map(static fn (array $row): string => $row['label'] . ': ' . $row['value'], $shippingRows));
        }
        $plain['PAYMENT'] = $plain['PAYMENT_TITLE'] !== '' ? $labels['PAYMENT'] . ': ' . $plain['PAYMENT_TITLE'] : '';
        if (count($paymentRows) > 1) {
            $plain['PAYMENT'] = $labels['PAYMENT'] . ":\n" . implode("\n", array_map(static fn (array $row): string => $row['label'] . ': ' . $row['value'], $paymentRows));
        }
        $html = $plain;
        $layout = new FileLayout('blocks', dirname(__DIR__, 2) . '/tmpl', ['client'=>'site']);
        $common = ['labels'=>$labels, 'order'=>$plain, 'items'=>$items, 'adjustments'=>$adjustments, 'contacts'=>$contacts, 'consents'=>$consents, 'shipping'=>$shippingRows, 'payment'=>$paymentRows];
        foreach (['ORDER_DETAILS','ITEMS','CONTACTS','STATUS_MESSAGE','SHIPPING','PAYMENT','ORDER_LINK','CONSENTS'] as $tag) {
            $html[$tag] = $plain[$tag] !== '' ? $layout->render($common + ['block'=>$tag]) : '';
        }
        // Только HTML, созданный макетом с экранированием значений, считается доверенным.
        $unsafe = array_values(array_diff(array_keys($plain), ['ORDER_DETAILS','ITEMS','CONTACTS','STATUS_MESSAGE','SHIPPING','PAYMENT','ORDER_LINK','CONSENTS']));
        return ['plain'=>$plain, 'html'=>$html, 'unsafe'=>$unsafe];
    }

}
