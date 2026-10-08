<?php
declare(strict_types=1);
namespace Dharma\Plugin\DharmaShopField\Basic\Type;

use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

/** Basic provider runtime. Uses only Joomla form fields; individual types can override these methods. */
trait NativeValue
{
    public function input(object $field, array $options): \SimpleXMLElement
    {
        $xml = new \SimpleXMLElement('<field />');
        $xml['name'] = 'f_' . (int) $field->id;
        $xml['label'] = (string) $field->title;
        $xml['translateLabel'] = 'false';
        $xml['required'] = (int) $field->required === 1 ? 'true' : 'false';
        $xml['filter'] = 'raw'; // Provider validates and sanitizes values before Table::store.
        $settings = $field->settings;
        switch ($this->id()) {
            case 'checkbox':
            case 'list':
                $single = $this->id() === 'checkbox' && ($settings['checkbox_mode'] ?? 'single') === 'single';
                $multiple = !$single && ($this->id() === 'checkbox' || (int) ($settings['list_multiple'] ?? 0) === 1);
                $xml['type'] = $multiple && $this->id() === 'checkbox' ? 'checkboxes' : 'list';
                $xml['validate'] = 'options';
                $xml['multiple'] = $multiple ? 'true' : 'false';
                if (!$multiple) { $xml->addChild('option', 'COM_DHARMASHOP_FIELD_EMPTY')->addAttribute('value', ''); }
                if ($single) {
                    $xml->addChild('option', 'JNO')->addAttribute('value', '0');
                    $xml->addChild('option', 'JYES')->addAttribute('value', '1');
                } else {
                    foreach ($options as $option) {
                        $node = $xml->addChild('option');
                        $node[0] = (string) $option->title;
                        $node['value'] = (string) $option->id;
                    }
                    $xml['translateOptions'] = 'false';
                    if ($xml['type'] == 'list') { $xml['layout'] = 'joomla.form.field.list-fancy-select'; }
                }
                break;
            case 'text':
                $xml['type'] = 'text';
                $xml['maxlength'] = (string) (($settings['text_maxlength'] ?? '') ?: 190);
                $xml['hint'] = (string) ($settings['text_hint'] ?? '');
                $xml['translateHint'] = 'false';
                break;
            case 'editor':
                $xml['type'] = 'editor';
                $xml['height'] = (string) (($settings['editor_height'] ?? '') ?: 300);
                $xml['buttons'] = 'false';
                break;
            case 'number':
                $this->numberInput($xml, $settings, 'number');
                break;
            case 'range':
                $xml['type'] = 'subform';
                $xml['multiple'] = 'false';
                $xml['layout'] = 'joomla.form.field.subform.default';
                $xml['filter'] = 'raw';
                $form = $xml->addChild('form');
                foreach (['from', 'to'] as $part) {
                    $bound = $form->addChild('field');
                    $bound['name'] = $part;
                    $bound['label'] = ($settings['range_' . $part . '_label'] ?? '') ?: Text::_('COM_DHARMASHOP_FIELD_BOUND_' . strtoupper($part));
                    $bound['translateLabel'] = 'false';
                    $this->numberInput($bound, $settings, 'range');
                    $this->describe($bound, $this->numericConstraints($settings, 'range'));
                }
                // Both bounds are required together, checked by the provider.
                $xml['required'] = 'false';
                break;
        }
        $hints = [];
        if ((int) $field->required === 1) { $hints[] = Text::_('COM_DHARMASHOP_FIELD_CONSTRAINT_REQUIRED'); }
        if ($this->id() === 'text') {
            $hints[] = Text::sprintf('COM_DHARMASHOP_FIELD_CONSTRAINT_LENGTH', (int) (($settings['text_maxlength'] ?? '') ?: 190));
        } elseif ($this->id() === 'number') {
            $hints = array_merge($hints, $this->numericConstraints($settings, 'number'));
        } elseif ($this->id() === 'range') {
            $hints[] = Text::_('COM_DHARMASHOP_FIELD_CONSTRAINT_RANGE');
        } elseif ($this->id() === 'list') {
            $hints[] = Text::_((int) ($settings['list_multiple'] ?? 0) === 1
                ? 'COM_DHARMASHOP_FIELD_CONSTRAINT_MULTIPLE' : 'COM_DHARMASHOP_FIELD_CONSTRAINT_SINGLE');
        }
        if (in_array($this->id(), ['number', 'range'], true) && trim((string) ($field->unit ?? '')) !== '') {
            $hints[] = Text::sprintf('COM_DHARMASHOP_FIELD_CONSTRAINT_UNIT', $field->unit);
        }
        $this->describe($xml, $hints);
        return $xml;
    }

    /** Descriptions use Joomla's native help block; settings are escaped before HTML rendering. */
    private function describe(\SimpleXMLElement $xml, array $hints): void
    {
        if (!$hints) { return; }
        $xml['description'] = htmlspecialchars(implode(' ', $hints), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $xml['translateDescription'] = 'false';
    }

    private function numericConstraints(array $settings, string $prefix): array
    {
        $hints = [];
        foreach (['min', 'max'] as $bound) {
            if (($settings[$prefix . '_' . $bound] ?? '') !== '') {
                $hints[] = Text::sprintf('COM_DHARMASHOP_FIELD_CONSTRAINT_' . strtoupper($bound), $settings[$prefix . '_' . $bound]);
            }
        }
        $precision = ($settings[$prefix . '_precision'] ?? '') !== '' ? (int) $settings[$prefix . '_precision'] : 6;
        $step = ($settings[$prefix . '_step'] ?? '') !== '' ? (string) $settings[$prefix . '_step']
            : ($precision === 0 ? '1' : '0.' . str_repeat('0', $precision - 1) . '1');
        $hints[] = Text::sprintf('COM_DHARMASHOP_FIELD_CONSTRAINT_STEP', $step, ($settings[$prefix . '_min'] ?? '') !== '' ? $settings[$prefix . '_min'] : '0');
        $hints[] = Text::sprintf('COM_DHARMASHOP_FIELD_CONSTRAINT_PRECISION', $precision);
        $hints[] = Text::_('COM_DHARMASHOP_FIELD_CONSTRAINT_NUMERIC_SIZE');
        return $hints;
    }

    private function numberInput(\SimpleXMLElement $xml, array $settings, string $prefix): void
    {
        $xml['type'] = 'number';
        $xml['filter'] = 'raw';
        foreach (['min', 'max', 'step'] as $part) {
            if (($settings[$prefix . '_' . $part] ?? '') !== '') { $xml[$part] = (string) $settings[$prefix . '_' . $part]; }
        }
        if (!isset($xml['step'])) {
            $precision = isset($settings[$prefix . '_precision']) && $settings[$prefix . '_precision'] !== '' ? (int) $settings[$prefix . '_precision'] : 6;
            $xml['step'] = $precision === 0 ? '1' : '0.' . str_repeat('0', $precision - 1) . '1';
        }
    }

    public function defaultValue(object $field, array $options): mixed
    {
        $settings = $field->settings;
        return match ($this->id()) {
            'text' => (string) ($settings['text_default'] ?? ''),
            'checkbox' => ($settings['checkbox_mode'] ?? 'single') === 'single'
                ? (string) ($settings['checkbox_default'] ?? '')
                : (isset($options[(int) ($settings['checkbox_default_option_id'] ?? 0)]) ? [(string) (int) $settings['checkbox_default_option_id']] : []),
            'range' => ['from' => '', 'to' => ''],
            default => '',
        };
    }

    public function decode(object $field, array $rows, ?string $text): mixed
    {
        if ($this->id() === 'editor') { return $text ?? ''; }
        if ($this->id() === 'range') { return ['from' => $rows[0]->num ?? '', 'to' => $rows[0]->num_end ?? '']; }
        if ($this->id() === 'checkbox' && ($field->settings['checkbox_mode'] ?? 'single') === 'single') {
            return isset($rows[0]->num) ? (string) (int) $rows[0]->num : '';
        }
        if ($this->store() === 'option') {
            $ids = array_values(array_filter(array_map(static fn (object $row): int => (int) $row->option_id, $rows)));
            // Штатные макеты checkboxes и list сравнивают значение с option строго, как строки: числа дали бы «ничего не выбрано».
            return $this->id() === 'checkbox' || (int) ($field->settings['list_multiple'] ?? 0) === 1 ? array_map('strval', $ids) : (isset($ids[0]) ? (string) $ids[0] : '');
        }
        return $this->store() === 'number' ? ($rows[0]->num ?? '') : ($rows[0]->str ?? '');
    }

    public function encode(object $field, array $options, mixed $value): array
    {
        $result = ['rows' => [], 'text' => null];
        $settings = $field->settings;
        $empty = $value === null || $value === '' || $value === [];
        if ($this->id() === 'range') {
            if (!is_array($value)) { $this->invalid(); }
            $from = $value['from'] ?? '';
            $to = $value['to'] ?? '';
            $empty = ($from === '' || $from === null) && ($to === '' || $to === null);
            if (!$empty) {
                if ($from === '' || $from === null || $to === '' || $to === null) {
                    $this->invalid('COM_DHARMASHOP_FIELD_VALUE_RANGE_BOTH');
                }
                $lower = $this->number($from, $settings, 'range');
                $upper = $this->number($to, $settings, 'range');
                if ((float) $lower > (float) $upper) { $this->invalid('COM_DHARMASHOP_FIELD_VALUE_RANGE_ORDER'); }
                $result['rows'][] = ['num' => $lower, 'num_end' => $upper];
            }
        } elseif (!$empty) {
            switch ($this->id()) {
                case 'checkbox':
                case 'list':
                    if ($this->id() === 'checkbox' && ($settings['checkbox_mode'] ?? 'single') === 'single') {
                        if (!is_scalar($value) || !in_array((string) $value, ['0', '1'], true)) { $this->invalid(); }
                        $result['rows'][] = ['num' => (int) $value];
                    } else {
                        $ids = [];
                        foreach ((array) $value as $id) {
                            if (!is_scalar($id) || !ctype_digit((string) $id) || !isset($options[(int) $id])) { $this->invalid(); }
                            $ids[(int) $id] = (int) $id;
                        }
                        if ($this->id() === 'list' && !(int) ($settings['list_multiple'] ?? 0) && count($ids) > 1) { $this->invalid('COM_DHARMASHOP_FIELD_VALUE_SINGLE'); }
                        foreach ($ids as $id) { $result['rows'][] = ['option_id' => $id]; }
                    }
                    break;
                case 'text':
                    if (!is_scalar($value)) { $this->invalid(); }
                    $text = trim((string) InputFilter::getInstance()->clean((string) $value, 'STRING'));
                    if (mb_strlen($text) > (int) (($settings['text_maxlength'] ?? '') ?: 190)) {
                        $this->invalid('COM_DHARMASHOP_FIELD_VALUE_LENGTH', (int) (($settings['text_maxlength'] ?? '') ?: 190));
                    }
                    if ($text !== '') { $result['rows'][] = ['str' => $text]; }
                    break;
                case 'editor':
                    if (!is_scalar($value) || strlen((string) $value) > 1000000) { $this->invalid(); }
                    $text = trim((string) InputFilter::getInstance([], [], 1, 1)->clean((string) $value, 'HTML'));
                    if (trim(strip_tags($text)) !== '' || preg_match('/<img\b/i', $text)) { $result['text'] = $text; }
                    break;
                case 'number':
                    $result['rows'][] = ['num' => $this->number($value, $settings, 'number')];
                    break;
            }
        }
        if ((int) $field->required === 1 && !$result['rows'] && $result['text'] === null) { $this->invalid('COM_DHARMASHOP_FIELD_VALUE_REQUIRED'); }
        return $result;
    }

    private function number(mixed $value, array $settings, string $prefix): string
    {
        if (!is_scalar($value)) { $this->invalid(); }
        $value = str_replace(',', '.', trim((string) $value));
        // DECIMAL(18,6): no exponents, at most twelve integral and six fractional digits.
        if (!preg_match('/^[+-]?\d{1,12}(?:\.\d{1,6})?$/D', $value)) { $this->invalid('COM_DHARMASHOP_FIELD_VALUE_NUMBER'); }
        $number = (float) $value;
        $precision = ($settings[$prefix . '_precision'] ?? '') !== '' ? (int) $settings[$prefix . '_precision'] : 6;
        $fraction = explode('.', $value, 2)[1] ?? '';
        if (strlen(rtrim($fraction, '0')) > $precision) { $this->invalid('COM_DHARMASHOP_FIELD_VALUE_PRECISION', $precision); }
        if (($settings[$prefix . '_min'] ?? '') !== '' && $number < (float) $settings[$prefix . '_min']) {
            $this->invalid('COM_DHARMASHOP_FIELD_VALUE_MIN', $settings[$prefix . '_min']);
        }
        if (($settings[$prefix . '_max'] ?? '') !== '' && $number > (float) $settings[$prefix . '_max']) {
            $this->invalid('COM_DHARMASHOP_FIELD_VALUE_MAX', $settings[$prefix . '_max']);
        }
        if (($settings[$prefix . '_step'] ?? '') !== '') {
            $steps = ($number - (float) (($settings[$prefix . '_min'] ?? '') ?: 0)) / (float) $settings[$prefix . '_step'];
            if (abs($steps - round($steps)) > 0.000001) {
                $this->invalid('COM_DHARMASHOP_FIELD_VALUE_STEP', $settings[$prefix . '_step'], ($settings[$prefix . '_min'] ?? '') !== '' ? $settings[$prefix . '_min'] : '0');
            }
        }
        // Preserve the submitted decimal exactly, without a float round trip.
        $negative = str_starts_with($value, '-');
        $integral = ltrim(explode('.', ltrim($value, '+-'), 2)[0], '0') ?: '0';
        $fraction = str_pad($fraction, 6, '0');
        $sign = $negative && ($integral !== '0' || trim($fraction, '0') !== '') ? '-' : '';
        return $sign . $integral . '.' . $fraction;
    }

    private function invalid(string $key = 'COM_DHARMASHOP_FIELD_VALUE_INVALID', mixed ...$args): never
    {
        throw new \InvalidArgumentException($args ? Text::sprintf($key, ...$args) : Text::_($key));
    }
}
