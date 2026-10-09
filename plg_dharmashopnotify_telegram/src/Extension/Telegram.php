<?php
declare(strict_types=1);

namespace Dharma\Plugin\DharmaShopNotify\Telegram\Extension;

use Dharma\Component\DharmaShop\Administrator\Notification\OrderNotification;
use Dharma\Component\DharmaShop\Administrator\Pricing\MoneyFormatter;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;
use Joomla\Http\HttpFactory;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** Telegram notifications use the official Bot API and the shared notification journal. */
final class Telegram extends CMSPlugin implements SubscriberInterface
{
    public function __construct(array $config, private readonly DatabaseInterface $db)
    {
        parent::__construct($config);
    }

    public static function getSubscribedEvents(): array
    {
        return ['onDharmaShopOrderNotify' => 'sendNotification'];
    }

    public function sendNotification(EventInterface $event): void
    {
        $settings = $event->getArgument('settings');
        $message = $event->getArgument('notification');

        if (!$settings instanceof Registry || !$message instanceof OrderNotification
            || (int) $settings->get('notify_telegram_enabled', 0) !== 1) {
            return;
        }

        $reason = $message->reason === 'created' ? 'created' : 'status';

        if ((int) $settings->get('notify_telegram_' . $reason, $reason === 'created' ? 1 : 0) !== 1) {
            return;
        }

        $token = trim((string) $settings->get('notify_telegram_bot_token', ''));
        $recipients = self::recipients($settings->get('notify_telegram_chats', []));

        if ($token === '' || !$recipients) {
            Log::add('Telegram notification skipped: bot token or chat recipients are not configured', Log::WARNING, 'com_dharmashop');

            return;
        }

        $service = $this->getApplication()->bootComponent('com_dharmashop')->getServices()->notifications();
        $text = $this->messageText($message, $settings, $reason);
        $retry = (bool) $event->getArgument('retry', false);

        foreach ($recipients as $recipient) {
            $deliveryId = $service->claim($message, 'telegram', 'admin', $recipient, $retry);

            if ($deliveryId === null) {
                continue;
            }

            $sent = false;
            $errorCode = '';

            try {
                $this->sendMessage($token, $recipient, $text);
                $sent = true;
            } catch (\Throwable $error) {
                $errorCode = $error instanceof \RuntimeException && preg_match('/^telegram_[a-z0-9_]+$/', $error->getMessage())
                    ? $error->getMessage()
                    : get_class($error);
                Log::add('Telegram delivery ' . $deliveryId . ' failed: ' . $errorCode, Log::ERROR, 'com_dharmashop');
            }

            $service->finish($deliveryId, $message->historyId, 'admin', $sent, $errorCode);
        }
    }

    /** @return list<string> Numeric chat IDs and public channel usernames are both supported by Bot API. */
    private static function recipients(mixed $value): array
    {
        $candidates = is_array($value)
            ? $value
            : (preg_split('/[\s,;]+/u', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $recipients = [];

        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);

            if (preg_match('/^-?\d{1,20}$/', $candidate) || preg_match('/^@[A-Za-z][A-Za-z0-9_]{4,31}$/', $candidate)) {
                $recipients[$candidate] = $candidate;
            }

            if (count($recipients) >= 50) {
                break;
            }
        }

        return array_values($recipients);
    }

    private function sendMessage(string $token, string $chatId, string $text): void
    {
        $body = json_encode([
            'chat_id' => $chatId,
            'text' => $text,
            'link_preview_options' => ['is_disabled' => true],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $response = (new HttpFactory())->getHttp(['timeout' => 8])->post(
            'https://api.telegram.org/bot' . $token . '/sendMessage',
            $body,
            ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            8,
        );

        $result = json_decode((string) $response->body, true);

        if ((int) $response->code < 200 || (int) $response->code >= 300
            || !is_array($result) || empty($result['ok'])) {
            $apiCode = is_array($result) ? (int) ($result['error_code'] ?? 0) : 0;
            throw new \RuntimeException($apiCode > 0 ? 'telegram_api_' . $apiCode : 'telegram_invalid_response');
        }
    }

    private function messageText(OrderNotification $message, Registry $settings, string $reason): string
    {
        $order = $message->order;
        $params = clone $settings;

        if ((string) $order['currency'] !== (string) $params->get('currency_code', 'RUB')) {
            $params->set('currency_symbol', (string) $order['currency']);
        }

        $money = new MoneyFormatter($params);
        $shopName = trim((string) $settings->get('shop_name', '')) ?: (string) $this->getApplication()->get('sitename');
        $date = (new Date((string) $order['created']))->format('d.m.Y H:i', true, false);
        $lines = [
            $reason === 'created' ? '🛒 Новый заказ №' . $order['number'] : '🔔 Изменение заказа №' . $order['number'],
            $shopName,
            'Дата: ' . $date,
            'Статус: ' . trim((string) ($order['status_title'] ?? '')),
        ];

        $statusMessage = trim((string) ($order['status_message'] ?? ''));

        if ($reason === 'status' && $statusMessage !== '') {
            $lines[] = $statusMessage;
        }

        if ((int) $settings->get('notify_telegram_items', 1) === 1 && $message->items) {
            $lines[] = '';
            $lines[] = 'Состав заказа:';
            $unknownTotal = false;

            foreach ($message->items as $item) {
                $variant = json_decode((string) ($item['params'] ?? ''), true) ?: [];
                $title = trim((string) $item['title']);

                if (!empty($variant['variant_title'])) {
                    $title .= ' — ' . trim((string) $variant['variant_title']);
                }

                $sku = trim((string) ($item['sku'] ?? ''));
                $free = !empty($variant['zero_free']);
                $price = $item['price'] === null ? $money->zeroText() : $money->price($item['price'], $free);
                $lineTotal = $item['total'] === null ? $money->zeroText() : $money->price($item['total'], $free);
                $qty = rtrim(rtrim(number_format((float) $item['qty'], 3, ',', ' '), '0'), ',') . ' ' . trim((string) $item['unit']);
                $lines[] = '• ' . $title . ($sku !== '' ? ' [' . $sku . ']' : '') . '\n  ' . $qty . ' × ' . $price . ' = ' . $lineTotal;

                if ($item['price'] === null || ($money->replacesZero() && (float) $item['price'] === 0.0 && !$free)) {
                    $unknownTotal = true;
                }
            }

            $total = $unknownTotal && (float) $order['total'] > 0
                ? $money->format($order['total']) . ' + ' . $money->zeroText()
                : ($unknownTotal ? $money->zeroText() : $money->format($order['total']));
            $lines[] = 'Итого: ' . $total;
        }

        if ((int) $settings->get('notify_telegram_contacts', 1) === 1) {
            $contacts = [];

            foreach (($message->contacts['fields'] ?? []) as $field) {
                $label = trim((string) ($field['label'] ?? ''));
                $value = trim((string) ($field['value'] ?? ''));

                if ($label !== '' && $value !== '') {
                    $contacts[] = $label . ': ' . $value;
                }
            }

            if ($contacts) {
                $lines[] = '';
                $lines[] = 'Контакты:';
                array_push($lines, ...$contacts);
            }
        }

        foreach (['shipping_title' => 'Доставка', 'payment_title' => 'Оплата'] as $key => $label) {
            $value = trim((string) ($order[$key] ?? ''));

            if ($value !== '') {
                $lines[] = $label . ': ' . $value;
            }
        }

        if ((int) $settings->get('notify_telegram_link', 1) === 1) {
            $lines[] = '';
            $lines[] = rtrim(Uri::root(), '/') . '/administrator/index.php?option=com_dharmashop&view=order&id=' . (int) $order['id'];
        }

        $text = implode("\n", $lines);

        return mb_strlen($text, 'UTF-8') <= 4000 ? $text : mb_substr($text, 0, 3980, 'UTF-8') . "\n…";
    }
}
