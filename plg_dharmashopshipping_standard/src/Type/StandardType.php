<?php
declare(strict_types=1);

namespace Dharma\Plugin\DharmaShopShipping\Standard\Type;

use Dharma\Component\DharmaShop\Administrator\Shipping\Context;
use Dharma\Component\DharmaShop\Administrator\Shipping\Method;
use Dharma\Component\DharmaShop\Administrator\Shipping\Quote;
use Dharma\Component\DharmaShop\Administrator\Shipping\TypeInterface;
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;

\defined('_JEXEC') or die;

/**
 * Стандартная доставка: фиксированная цена, бесплатно от суммы, ограничения по сумме заказа, срок
 * и конструктор полей покупателя (город, адрес, индекс, комментарий и т. п.).
 */
final class StandardType implements TypeInterface
{
    private const FIELD_TYPES = ['text', 'textarea', 'tel', 'select'];

    public function id(): string
    {
        return 'standard';
    }

    public function label(): string
    {
        return 'PLG_DHARMASHOPSHIPPING_STANDARD_TYPE';
    }

    public function description(): string
    {
        return 'PLG_DHARMASHOPSHIPPING_STANDARD_DESC';
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
                throw new \DomainException('PLG_DHARMASHOPSHIPPING_STANDARD_ERROR_' . $label);
            }

            return round((float) $text, 2);
        };

        $normalized = [
            'price' => $money($settings['price'] ?? 0, 'PRICE'),
            'free_from' => $money($settings['free_from'] ?? 0, 'FREE_FROM'),
            'min_total' => $money($settings['min_total'] ?? 0, 'MIN_TOTAL'),
            'max_total' => $money($settings['max_total'] ?? 0, 'MAX_TOTAL'),
            'term' => mb_substr(trim(strip_tags((string) ($settings['term'] ?? ''))), 0, 100),
            'note' => mb_substr(trim(strip_tags((string) ($settings['note'] ?? ''))), 0, 500),
            'fields' => $this->normalizeFields((array) ($settings['fields'] ?? [])),
        ];

        if ($normalized['max_total'] > 0 && $normalized['min_total'] > $normalized['max_total']) {
            throw new \DomainException('PLG_DHARMASHOPSHIPPING_STANDARD_ERROR_RANGE');
        }

        return $normalized;
    }

    /** Определения полей: ключ создаётся из подписи (латиница, уникальный), тип и ширина ограничены списками. */
    private function normalizeFields(array $rows): array
    {
        $fields = [];
        $used = [];

        foreach ($rows as $row) {
            $row = (array) $row;
            $label = mb_substr(trim(strip_tags((string) ($row['label'] ?? ''))), 0, 100);

            if ($label === '') {
                continue;
            }

            $type = in_array((string) ($row['type'] ?? ''), self::FIELD_TYPES, true) ? (string) $row['type'] : 'text';
            $key = str_replace('-', '_', ApplicationHelper::stringURLSafe($label)) ?: 'field';
            $key = substr($key, 0, 40);
            $base = $key;

            for ($n = 2; isset($used[$key]); $n++) {
                $key = $base . '_' . $n;
            }

            $used[$key] = true;
            $options = [];

            if ($type === 'select') {
                foreach (preg_split('/\R/u', (string) ($row['options'] ?? '')) ?: [] as $option) {
                    $option = mb_substr(trim(strip_tags($option)), 0, 100);

                    if ($option !== '') {
                        $options[] = $option;
                    }
                }

                $options = array_slice(array_values(array_unique($options)), 0, 50);

                if (!$options) {
                    throw new \DomainException('PLG_DHARMASHOPSHIPPING_STANDARD_ERROR_OPTIONS');
                }
            }

            $fields[] = [
                'key' => $key,
                'label' => $label,
                'type' => $type,
                'required' => (int) ($row['required'] ?? 0) === 1 ? 1 : 0,
                'width' => (string) ($row['width'] ?? 'full') === 'half' ? 'half' : 'full',
                'placeholder' => mb_substr(trim(strip_tags((string) ($row['placeholder'] ?? ''))), 0, 100),
                // Строкой (по варианту в строке), как вводится в форме: массив в поле формы ломает вывод textarea.
                'options' => implode("\n", $options),
            ];
        }

        return $fields;
    }

    public function quote(Method $method, Context $context): ?Quote
    {
        $p     = $method->params;
        $min   = (float) ($p['min_total'] ?? 0);
        $max   = (float) ($p['max_total'] ?? 0);
        $price = (float) ($p['price'] ?? 0);
        $from  = (float) ($p['free_from'] ?? 0);

        if (($min > 0 && $context->total < $min) || ($max > 0 && $context->total > $max)) {
            return null;
        }

        // Бесплатно: нулевая цена или итог заказа (после скидок, без доставки) не меньше порога.
        $free = $price <= 0 || ($from > 0 && $context->total >= $from);

        return new Quote($free ? 0.0 : round($price, 2), (string) ($p['term'] ?? ''), (string) ($p['note'] ?? ''), $free);
    }

    /** Подпись способа (например, «менеджер уточнит детали») идёт в боковую панель, заказ и письма отдельной строкой. */
    public function describe(Method $method, Quote $quote): array
    {
        return $quote->note !== '' ? [['label' => Text::_('PLG_DHARMASHOPSHIPPING_STANDARD_ROW_NOTE'), 'value' => $quote->note]] : [];
    }

    public function validateInput(Method $method, Context $context, array $input): array
    {
        $data = [];
        $rows = [];
        $errors = [];

        foreach ($this->fieldsOf($method) as $field) {
            $key = (string) $field['key'];
            $raw = trim((string) preg_replace('/[ \t]+/u', ' ', strip_tags((string) ($input[$key] ?? ''))));
            $raw = (string) preg_replace('/\R{3,}/u', "\n\n", $raw);

            if ($raw === '') {
                if ((int) $field['required'] === 1) {
                    $errors[$key] = 'PLG_DHARMASHOPSHIPPING_STANDARD_FIELD_REQUIRED_ERROR';
                }

                continue;
            }

            $limit = $field['type'] === 'textarea' ? 1000 : 255;

            if (mb_strlen($raw) > $limit) {
                $errors[$key] = 'PLG_DHARMASHOPSHIPPING_STANDARD_FIELD_LONG';
            } elseif ($field['type'] === 'select' && !in_array($raw, (array) $field['options'], true)) {
                $errors[$key] = 'PLG_DHARMASHOPSHIPPING_STANDARD_FIELD_INVALID';
            } elseif ($field['type'] === 'tel' && !preg_match('/^[0-9+()\-\s.]{5,25}$/D', $raw)) {
                $errors[$key] = 'PLG_DHARMASHOPSHIPPING_STANDARD_FIELD_INVALID';
            } else {
                $data[$key] = $raw;
                $rows[] = ['label' => (string) $field['label'], 'value' => $raw];
            }
        }

        return ['data' => $data, 'rows' => $rows, 'errors' => $errors];
    }

    /** Определения полей способа; если настройки ещё не сохранялись — город и адрес. */
    private function fieldsOf(Method $method): array
    {
        if (isset($method->params['fields']) && is_array($method->params['fields'])) {
            return array_map(static function (array $field): array {
                $options = $field['options'] ?? [];
                $field['options'] = array_values(array_filter(is_array($options) ? $options : (preg_split('/\R/u', (string) $options) ?: []), static fn ($option): bool => trim((string) $option) !== ''));

                return $field;
            }, array_values(array_filter($method->params['fields'], 'is_array')));
        }

        $line = static fn (string $key, string $label, string $type, int $required, string $width, string $placeholder = '', array $options = []): array
            => ['key' => $key, 'label' => $label, 'type' => $type, 'required' => $required, 'width' => $width, 'placeholder' => $placeholder, 'options' => $options];

        return [
            $line('gorod', Text::_('PLG_DHARMASHOPSHIPPING_STANDARD_DEFAULT_CITY'), 'text', 1, 'half'),
            $line('ulitsa_i_dom', Text::_('PLG_DHARMASHOPSHIPPING_STANDARD_DEFAULT_STREET'), 'text', 1, 'half'),
            $line('kvartira', Text::_('PLG_DHARMASHOPSHIPPING_STANDARD_DEFAULT_FLAT'), 'text', 0, 'half'),
            $line('podezd', Text::_('PLG_DHARMASHOPSHIPPING_STANDARD_DEFAULT_ENTRANCE'), 'text', 0, 'half'),
            $line('etazh', Text::_('PLG_DHARMASHOPSHIPPING_STANDARD_DEFAULT_FLOOR'), 'text', 0, 'half'),
            $line('domofon', Text::_('PLG_DHARMASHOPSHIPPING_STANDARD_DEFAULT_INTERCOM'), 'text', 0, 'half'),
            $line('kommentariy', Text::_('PLG_DHARMASHOPSHIPPING_STANDARD_DEFAULT_COMMENT'), 'textarea', 0, 'full'),
        ];
    }

    public function ajax(Method $method, Context $context, string $action, array $input): array
    {
        return ['ok' => false, 'error' => 'Unknown action'];
    }

    public function render(array $displayData): string
    {
        $path = PluginHelper::getLayoutPath('dharmashopshipping', 'standard', 'default');
        $displayData['fields'] = $this->fieldsOf($displayData['method']);

        return (static function (string $__path, array $displayData): string {
            ob_start();
            include $__path;

            return (string) ob_get_clean();
        })($path, $displayData);
    }
}
