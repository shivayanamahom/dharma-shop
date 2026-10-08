<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Shipping;

\defined('_JEXEC') or die;

/** Тип способа доставки (C80). Тип регистрирует плагин группы dharmashopshipping; способов одного типа может быть много. */
interface TypeInterface
{
    /** Идентификатор типа = element плагина (`standard`); хранится в `shipping_methods.plugin`. */
    public function id(): string;

    /** Языковой ключ названия типа. */
    public function label(): string;

    /** Языковой ключ краткого описания типа (подсказка в форме). */
    public function description(): string;

    /** Form XML настроек: `<fields name="params"><fieldset name="type_<id>">`, поля с `showon=".plugin:<id>"`. */
    public function settingsPath(): string;

    /** Проверка и нормализация настроек при сохранении способа; при ошибке — \DomainException с языковым ключом. */
    public function normalizeSettings(array $settings): array;

    /** Расчёт для корзины; null — способ недоступен для этой корзины. Только по серверным данным. */
    public function quote(Method $method, Context $context): ?Quote;

    /**
     * Постоянные сведения о способе для покупателя и менеджера: адрес пункта выдачи, телефон, часы работы.
     * Показываются у варианта на странице оформления, в боковой панели, в заказе и в письмах.
     *
     * @return  array<int, array{label: string, value: string}>  обычный текст (экранируется при выводе); пустые значения не возвращаем
     */
    public function describe(Method $method, Quote $quote): array;

    /**
     * Данные, введённые покупателем при выборе способа (город, адрес, комментарий и т. п.).
     *
     * @param   array<string, mixed>  $input  значения из `shipping[<id>]` (строки, уже из POST)
     *
     * @return  array{data: array<string, mixed>, rows: array<int, array{label: string, value: string}>, errors: array<string, string>, quote?: Quote}
     *          data — машинные значения для снимка; rows — те же сведения для показа (подпись и значение, обычный текст);
     *          errors — [ключ поля => языковой ключ сообщения]; quote — необязательный итоговый расчёт по введённым данным
     *          (заменяет предварительный: так считается цена, зависящая от адреса); только по серверным данным
     */
    public function validateInput(Method $method, Context $context, array $input): array;

    /**
     * Динамический запрос типа с оформления (подсказки адреса, расчёт цены по адресу): `shipping.ajax` ядра передаёт сюда действие и данные.
     * Ответ — массив для JSON (`ok`, остальное — на усмотрение типа); неизвестное действие — `['ok' => false, 'error' => <текст>]`.
     *
     * @param   array<string, mixed>  $input  данные запроса (строки)
     *
     * @return  array<string, mixed>
     */
    public function ajax(Method $method, Context $context, string $action, array $input): array;

    /**
     * HTML варианта способа на странице оформления. Плагин выводит свой макет (его можно переопределить в шаблоне).
     *
     * @param   array<string, mixed>  $displayData  method, quote, selected, id, values, errors, info (результат describe), price_text, total_text, free_text
     */
    public function render(array $displayData): string;
}
