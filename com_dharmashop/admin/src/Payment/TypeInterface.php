<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Payment;

\defined('_JEXEC') or die;

/**
 * Тип способа оплаты (C83). Тип регистрирует плагин группы dharmashoppayment; способов одного типа может быть много
 * («Наличными», «Картой курьеру» — разные способы типа «Стандартная оплата»). Правила и примеры — docs/PAYMENTS.md.
 */
interface TypeInterface
{
    /** Идентификатор типа = element плагина (`standard`); хранится в `payment_methods.plugin`. */
    public function id(): string;

    /** Языковой ключ названия типа. */
    public function label(): string;

    /** Языковой ключ краткого описания типа (подсказка в форме). */
    public function description(): string;

    /** Form XML настроек: `<fields name="params"><fieldset name="type_<id>">`, поля с `showon=".plugin:<id>"`. */
    public function settingsPath(): string;

    /** Проверка и нормализация настроек при сохранении способа; при ошибке — \DomainException с языковым ключом. */
    public function normalizeSettings(array $settings): array;

    /** Доступен ли способ для этой корзины (например, пределы суммы заказа). Только по серверным данным. */
    public function available(Method $method, Context $context): bool;

    /**
     * Постоянные сведения о способе: реквизиты, инструкция, срок. Показываются у варианта на оформлении, в заказе и в письмах.
     *
     * @return  array<int, array{label: string, value: string}>  обычный текст; пустые значения не возвращаем
     */
    public function describe(Method $method, Context $context): array;

    /**
     * Данные, введённые покупателем при выборе способа (например, номер телефона для СБП).
     *
     * @param   array<string, mixed>  $input  значения из `payment[<id>]`
     *
     * @return  array{data: array<string, mixed>, rows: array<int, array{label: string, value: string}>, errors: array<string, string>}
     */
    public function validateInput(Method $method, Context $context, array $input): array;

    /**
     * Динамический запрос с оформления (если типу нужен); неизвестное действие — `['ok' => false, 'error' => <текст>]`.
     *
     * @param   array<string, mixed>  $input
     *
     * @return  array<string, mixed>
     */
    public function ajax(Method $method, Context $context, string $action, array $input): array;

    /**
     * HTML панели способа на оформлении (описание, поля). Плитку-вкладку рисует страница оформления. Макет можно переопределить в шаблоне.
     *
     * @param   array<string, mixed>  $displayData  method, selected, id, values, errors, info (результат describe), image_url
     */
    public function render(array $displayData): string;

    /**
     * Начало оплаты после создания заказа: не нужна (`Initiation::none`), переход на внешний шлюз (`redirect`) или страница оплаты на сайте (`page`).
     * Вызывается один раз; ответ шлюзу о создании платежа может сохранить свои данные в `$order->data`.
     */
    public function initiate(Method $method, OrderPayment $order, Urls $urls): Initiation;

    /**
     * HTML страницы оплаты на сайте (для `Initiation::page`): реквизиты, форма, кнопки. Макет можно переопределить в шаблоне.
     *
     * @param   array<string, mixed>  $displayData  method, order (OrderPayment), urls (Urls), info, status, message
     */
    public function renderPayment(array $displayData): string;

    /**
     * Обратный вызов шлюза: уведомление сервера (webhook, `notify`) или возврат покупателя (`return`) и другие действия типа.
     * Тип сам проверяет подлинность запроса (подпись, секрет). Результат применяет ядро (статус оплаты, журнал, статус заказа).
     *
     * @param   array<string, mixed>  $input  параметры запроса (строки из GET/POST)
     */
    public function handle(Method $method, OrderPayment $order, string $action, array $input): Outcome;
}
