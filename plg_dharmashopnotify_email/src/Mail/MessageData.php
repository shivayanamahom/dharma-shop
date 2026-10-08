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
            $price = $item['price'] === null ? $money->zeroText() : $money->price($item['price']);
            $total = $item['total'] === null ? $money->zeroText() : $money->price($item['total']);
            $productId = (int) ($item['available_product_id'] ?? 0);
            $url = $productId > 0 ? $root . '/index.php?option=com_dharmashop&view=product&id=' . $productId : '';
            $image = $productId > 0 ? $this->imageUrl((string) ($item['cover'] ?? ''), $root) : '';
            $items[] = ['title'=>$title, 'sku'=>$item['sku'], 'qty'=>$qty, 'price'=>$price, 'total'=>$total, 'url'=>$url, 'image'=>$image];
            $plainItems[] = $title . ($item['sku'] !== '' ? ' [' . $item['sku'] . ']' : '') . "\n" . $qty . ' × ' . $price . ' = ' . $total;
            if ($item['price'] === null || ($money->replacesZero() && (float) $item['price'] === 0.0)) $unknown = true;
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
        if ($audience === 'customer' && !empty($order['user_id']) && (int) $settings->get('notify_customer_link', 1) === 1) $link = $root . '/index.php?option=com_dharmashop&view=order&id=' . (int) $order['id'];
        $shopName = trim((string) $settings->get('shop_name', '')) ?: $siteName;
        $shopEmail = trim((string) $settings->get('shop_email', ''));
        if (!MailHelper::isEmailAddress($shopEmail)) $shopEmail = '';
        $shopPhone = trim((string) $settings->get('shop_phone', ''));
        $shopAddress = trim((string) $settings->get('shop_address', ''));
        $logo = $this->imageUrl((string) $settings->get('shop_logo', ''), $root);
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
        $plain['PAYMENT'] = $plain['PAYMENT_TITLE'] !== '' ? $labels['PAYMENT'] . ': ' . $plain['PAYMENT_TITLE'] : '';
        $html = $plain;
        $layout = new FileLayout('blocks', dirname(__DIR__, 2) . '/tmpl', ['client'=>'site']);
        $common = ['labels'=>$labels, 'order'=>$plain, 'items'=>$items, 'adjustments'=>$adjustments, 'contacts'=>$contacts, 'consents'=>$consents];
        foreach (['ORDER_DETAILS','ITEMS','CONTACTS','STATUS_MESSAGE','SHIPPING','PAYMENT','ORDER_LINK','CONSENTS'] as $tag) {
            $html[$tag] = $plain[$tag] !== '' ? $layout->render($common + ['block'=>$tag]) : '';
        }
        // Только HTML, созданный макетом с экранированием значений, считается доверенным.
        $unsafe = array_values(array_diff(array_keys($plain), ['ORDER_DETAILS','ITEMS','CONTACTS','STATUS_MESSAGE','SHIPPING','PAYMENT','ORDER_LINK','CONSENTS']));
        return ['plain'=>$plain, 'html'=>$html, 'unsafe'=>$unsafe];
    }

    private function imageUrl(string $value, string $root): string
    {
        $value = trim(HTMLHelper::_('cleanImageURL', $value)->url);
        if ($value === '' || str_contains($value, '\\') || preg_match('/[\x00-\x20]/', $value)) return '';
        $url = preg_match('~^https?://~i', $value) ? $value : $root . '/' . ltrim($value, '/');
        if (str_starts_with($value, '//') || (!preg_match('~^https?://~i', $value) && preg_match('~^[^/]*:~', $value))) return '';
        return filter_var($url, FILTER_VALIDATE_URL) && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http','https'], true) ? $url : '';
    }
}
