<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    plg_dharmashoppayment_standard
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Plugin\DharmaShopPayment\Standard\Type;

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
 * Стандартная оплата: оплата вне сайта (наличными или картой курьеру, по счёту, при самовывозе). Страницы оплаты нет:
 * покупатель видит инструкцию на оформлении, в заказе и в письме. Способов этого типа можно завести несколько («Наличными», «Картой курьеру»).
 */
final class StandardType implements TypeInterface
{
    public function id(): string
    {
        return 'standard';
    }

    public function label(): string
    {
        return 'PLG_DHARMASHOPPAYMENT_STANDARD_TYPE';
    }

    public function description(): string
    {
        return 'PLG_DHARMASHOPPAYMENT_STANDARD_DESC';
    }

    public function settingsPath(): string
    {
        return dirname(__DIR__, 2) . '/forms/settings.xml';
    }

    public function normalizeSettings(array $settings): array
    {
        $money = static function (mixed $value, string $label): float {
            $text = str_replace([' ', ','], ['', '.'], trim((string) $value));

            if ($text === '') {
                return 0.0;
            }

            if (!is_numeric($text) || (float) $text < 0 || (float) $text > 1000000000) {
                throw new \DomainException('PLG_DHARMASHOPPAYMENT_STANDARD_ERROR_' . $label);
            }

            return round((float) $text, 2);
        };

        $normalized = [
            'instruction' => mb_substr(trim(strip_tags((string) ($settings['instruction'] ?? ''))), 0, 2000),
            'min_total' => $money($settings['min_total'] ?? 0, 'MIN_TOTAL'),
            'max_total' => $money($settings['max_total'] ?? 0, 'MAX_TOTAL'),
        ];

        if ($normalized['max_total'] > 0 && $normalized['min_total'] > $normalized['max_total']) {
            throw new \DomainException('PLG_DHARMASHOPPAYMENT_STANDARD_ERROR_RANGE');
        }

        return $normalized;
    }

    public function available(Method $method, Context $context): bool
    {
        $min = (float) ($method->params['min_total'] ?? 0);
        $max = (float) ($method->params['max_total'] ?? 0);

        return !(($min > 0 && $context->total < $min) || ($max > 0 && $context->total > $max));
    }

    /** Инструкция идёт в боковую панель, заказ и письма отдельной строкой. */
    public function describe(Method $method, Context $context): array
    {
        $text = (string) ($method->params['instruction'] ?? '');

        return $text !== '' ? [['label' => Text::_('PLG_DHARMASHOPPAYMENT_STANDARD_ROW_INSTRUCTION'), 'value' => $text]] : [];
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
        return self::include(PluginHelper::getLayoutPath('dharmashoppayment', 'standard', 'default'), $displayData);
    }

    public function initiate(Method $method, OrderPayment $order, Urls $urls): Initiation
    {
        return Initiation::none();
    }

    public function renderPayment(array $displayData): string
    {
        return '';
    }

    public function handle(Method $method, OrderPayment $order, string $action, array $input): Outcome
    {
        return Outcome::ignored();
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
