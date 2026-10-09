<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    plg_dharmashoppayment_sandbox
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Plugin\DharmaShopPayment\Sandbox\Type;

use Dharma\Component\DharmaShop\Administrator\Payment\Context;
use Dharma\Component\DharmaShop\Administrator\Payment\Initiation;
use Dharma\Component\DharmaShop\Administrator\Payment\Method;
use Dharma\Component\DharmaShop\Administrator\Payment\Outcome;
use Dharma\Component\DharmaShop\Administrator\Payment\OrderPayment;
use Dharma\Component\DharmaShop\Administrator\Payment\TypeInterface;
use Dharma\Component\DharmaShop\Administrator\Payment\Urls;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;

\defined('_JEXEC') or die;

/**
 * Тестовая оплата («песочница»): имитация платёжного шлюза для проверки всего процесса — заказ, переход на страницу оплаты, успех, отказ,
 * отмена, обратный вызов. Деньги не списываются. Служит образцом для настоящих плагинов шлюзов (docs/PAYMENTS.md).
 * Действия: `result` — переход покупателя со страницы оплаты, `notify` — имитация уведомления сервера (webhook); оба подписаны секретом заказа.
 */
final class SandboxType implements TypeInterface
{
    private const RESULTS = ['paid', 'failed', 'cancelled'];

    public function id(): string
    {
        return 'sandbox';
    }

    public function label(): string
    {
        return 'PLG_DHARMASHOPPAYMENT_SANDBOX_TYPE';
    }

    public function description(): string
    {
        return 'PLG_DHARMASHOPPAYMENT_SANDBOX_DESC';
    }

    public function settingsPath(): string
    {
        return dirname(__DIR__, 2) . '/forms/settings.xml';
    }

    public function normalizeSettings(array $settings): array
    {
        return ['instruction' => mb_substr(trim(strip_tags((string) ($settings['instruction'] ?? ''))), 0, 1000)];
    }

    public function available(Method $method, Context $context): bool
    {
        return true;
    }

    public function describe(Method $method, Context $context): array
    {
        $text = (string) ($method->params['instruction'] ?? '');

        return $text !== '' ? [['label' => Text::_('PLG_DHARMASHOPPAYMENT_SANDBOX_ROW_NOTE'), 'value' => $text]] : [];
    }

    public function validateInput(Method $method, Context $context, array $input): array
    {
        return ['data' => [], 'rows' => [], 'errors' => []];
    }

    public function ajax(Method $method, Context $context, string $action, array $input): array
    {
        return ['ok' => false, 'error' => 'Unknown action'];
    }

    public function render(array $displayData): string
    {
        return self::include(PluginHelper::getLayoutPath('dharmashoppayment', 'sandbox', 'default'), $displayData);
    }

    /** После заказа покупатель попадает на страницу оплаты сайта (макет `payment.php`). */
    public function initiate(Method $method, OrderPayment $order, Urls $urls): Initiation
    {
        return Initiation::page(['gateway' => 'sandbox']);
    }

    public function renderPayment(array $displayData): string
    {
        /** @var OrderPayment $order */
        $order = $displayData['order'];
        /** @var Urls $urls */
        $urls = $displayData['urls'];
        $displayData['links'] = [];

        foreach (self::RESULTS as $result) {
            $displayData['links'][$result] = $urls->callback('result', ['result' => $result, 'sig' => self::sign($order, $result)]);
        }

        return self::include(PluginHelper::getLayoutPath('dharmashoppayment', 'sandbox', 'payment'), $displayData);
    }

    public function handle(Method $method, OrderPayment $order, string $action, array $input): Outcome
    {
        if (!in_array($action, ['result', 'notify'], true)) {
            return Outcome::ignored();
        }

        $result = (string) ($input['result'] ?? '');
        $sig = (string) ($input['sig'] ?? '');

        // Подпись секретом заказа: без неё платёж изменить нельзя (так же, как проверяет подпись настоящий шлюз).
        if (!in_array($result, self::RESULTS, true) || $sig === '' || !hash_equals(self::sign($order, $result), $sig)) {
            return Outcome::ignored();
        }

        $ref = 'SBX-' . $order->number . '-' . substr(hash('sha256', $order->key . $result), 0, 8);

        return match ($result) {
            'paid' => Outcome::paid($ref, $order->total),
            'failed' => Outcome::failed('Declined in sandbox', $ref),
            default => Outcome::cancelled('Cancelled in sandbox', $ref),
        };
    }

    /** Подпись результата для заказа: HMAC секретом заказа. */
    public static function sign(OrderPayment $order, string $result): string
    {
        return substr(hash_hmac('sha256', $order->id . '|' . $result . '|' . $order->number, $order->key), 0, 32);
    }

    private static function include(string $path, array $displayData): string
    {
        return (static function (string $__path, array $displayData): string {
            ob_start();
            include $__path;

            return (string) ob_get_clean();
        })($path, $displayData);
    }
}
