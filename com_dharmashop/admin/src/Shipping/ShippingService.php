<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Shipping;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * Способы доставки для оформления заказа: список доступных с расчётом и проверка выбора покупателя.
 * Цена и доступность считаются плагином типа по серверным данным; суммы из браузера не принимаются.
 */
final class ShippingService
{
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * Опубликованные способы с доступным типом (плагин включён), по порядку, с учётом уровня доступа посетителя.
     *
     * @return  Method[]
     */
    public function methods(): array
    {
        $identity = Factory::getApplication()->getIdentity();
        $levels   = $identity ? array_map('intval', $identity->getAuthorisedViewLevels()) : [1];
        $query    = $this->db->createQuery()
            ->select(['id', 'title', 'plugin', 'description', 'params', 'is_default'])
            ->from($this->db->quoteName('#__dharmashop_shipping_methods'))
            ->where('state = 1')
            ->whereIn('access', $levels)
            ->order('ordering, id');
        $registry = TypeRegistry::current();
        $methods  = [];

        foreach ((array) $this->db->setQuery($query)->loadObjectList() as $row) {
            if ($registry->find((string) $row->plugin) !== null) {
                $methods[] = Method::fromRow($row);
            }
        }

        return $methods;
    }

    /** Есть ли хотя бы один способ: тогда выбор доставки обязателен при оформлении. */
    public function hasMethods(): bool
    {
        return $this->methods() !== [];
    }

    /**
     * Способы, доступные для этой корзины, с расчётом.
     *
     * @return  array<int, array{method: Method, type: TypeInterface, quote: Quote, info: array<int, array{label: string, value: string}>}>  ключ — id способа
     */
    public function options(Context $context): array
    {
        $registry = TypeRegistry::current();
        $options  = [];

        foreach ($this->methods() as $method) {
            $type  = $registry->find($method->plugin);
            $quote = $type?->quote($method, $context);

            if ($type !== null && $quote !== null && is_finite($quote->price) && $quote->price >= 0) {
                $options[$method->id] = ['method' => $method, 'type' => $type, 'quote' => $quote, 'info' => self::cleanRows($type->describe($method, $quote))];
            }
        }

        return $options;
    }

    /**
     * Проверяет выбор покупателя: способ существует, доступен и данные корректны.
     *
     * @param   array<string, mixed>  $input  значения из `shipping[<id>]`
     *
     * @throws  \DomainException  недопустимый способ; ошибки полей — в getErrors() через ShippingInputException
     */
    public function resolve(int $methodId, Context $context, array $input): Selection
    {
        $options = $this->options($context);

        if (!isset($options[$methodId])) {
            throw new ShippingInputException(Text::_('COM_DHARMASHOP_SHIPPING_UNAVAILABLE'));
        }

        $option = $options[$methodId];
        $result = $option['type']->validateInput($option['method'], $context, $input);

        if ($result['errors']) {
            throw new ShippingInputException(Text::_('COM_DHARMASHOP_SHIPPING_FIELDS_ERROR'), $result['errors']);
        }

        $rows = array_merge($option['type']->describe($option['method'], $option['quote']), $result['rows']);

        return new Selection($option['method'], $option['quote'], $result['data'], self::cleanRows($rows));
    }

    /**
     * Строки для показа: только подпись и значение обычным текстом, без пустых и слишком длинных.
     *
     * @param   array<int, mixed>  $rows
     *
     * @return  array<int, array{label: string, value: string}>
     */
    public static function cleanRows(array $rows): array
    {
        $clean = [];

        foreach ($rows as $row) {
            $label = mb_substr(trim(strip_tags((string) ($row['label'] ?? ''))), 0, 100);
            $value = mb_substr(trim(strip_tags((string) ($row['value'] ?? ''))), 0, 1000);

            if ($label !== '' && $value !== '') {
                $clean[] = ['label' => $label, 'value' => $value];
            }
        }

        return $clean;
    }

    /** Идентификатор способа по умолчанию среди доступных (для предварительного выбора). */
    public function defaultId(array $options): int
    {
        foreach ($options as $id => $option) {
            if ($option['method']->isDefault) {
                return (int) $id;
            }
        }

        return (int) (array_key_first($options) ?? 0);
    }
}
