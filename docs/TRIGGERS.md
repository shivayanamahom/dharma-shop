# Справочник событий и триггеров

События `onDharmaShop*` — публичные точки расширения компонента. Эта страница собирает контракт событий, которые уже используются, и помечает зарезервированные/предложенные события как неработающие. Каноническая матрица подсистем и контрактов находится в [архитектуре, раздел 6](ARCHITECTURE.md#6-события); каталог расширений и примеры — в [справке разработчика](DEVELOPER_GUIDE.md).

Перед тем как подписываться на событие, проверьте пометку «есть» или «план»: наличие имени в таблице само по себе не означает, что код его отправляет. Подписчики Joomla реализуют `SubscriberInterface`; параметры события читаются типизированным объектом/геттерами. Для макетных событий результат — HTML через `addResult()`, а не `return` или `echo`.

## События регистрации и жизненного цикла

Полный список с аргументами — **`ARCHITECTURE.md` §6** (каталог, поля, корзина, заказ, служебные). Здесь — только то, что добавилось или уточнилось, и текущий статус.

| Событие | Статус | Примечание |
|---|---|---|
| `onDharmaShopProductBeforeSave` / `AfterSave`, `VariantBeforeSave` / `AfterSave`, `ProductBeforeDelete` / `AfterDelete` | план (этап 1, не подключены) | сейчас сохранение идёт без вызова событий; подключить вместе с первым плагином |
| `onDharmaShopProductPrepareForm` | план | форма товара собирается в `ProductModel::preprocessForm` |
| `onDharmaShopListingQuery`, `ProductPrepare`, `ProductDisplay`, `PriceResolve`, `StockChange`, `IndexRebuild` | план | витрина и индекс |
| `onDharmaShopFieldTypesCollect` (группа `dharmashopfield`) | **есть (C53/C59)** | Аргумент `registry: Administrator\Fields\TypeRegistry`; провайдер добавляет `TypeInterface` через `register()`. Для ввода/проверки значений также реализует `ValueTypeInterface` (`input/defaultValue/decode/encode`), без новых событий и SQL в провайдере. Дубликаты ID запрещены, после dispatch реестр закрыт. |
| `onDharmaShopPaymentTypesCollect` (группа `dharmashoppayment`) | **есть (C83)** | Аргумент `registry: Administrator\Payment\TypeRegistry`; плагин регистрирует `Payment\TypeInterface` (id = element плагина). Контракт и примеры — [PAYMENTS.md](PAYMENTS.md). |
| `onDharmaShopShippingTypesCollect` (группа `dharmashopshipping`) | **есть (C80)** | Аргумент `registry: Administrator\Shipping\TypeRegistry`; плагин регистрирует `TypeInterface` (id = element плагина). Правила — [SHIPPING.md](SHIPPING.md). |
| `onDharmaShopImagePrepare` (группа `dharmashop`) | **есть (C79)** | Перед выводом URL: Joomla Event, `request: Administrator\Image\ImageRequest` (readonly source/context, изменяемый url), `settings: Registry`. Плагин размеров меняет request.url на кешированную производную. Без плагина остаётся безопасный абсолютный URL оригинала. Контексты/добавление: IMAGE_RESIZE.md. |
| Остальные события полей из ARCHITECTURE §6.2 | план, этап 2 | Ввод/значения/фильтрация/вывод ещё не подключены. |
| `onDharmaShopCartBeforeAdd` | **есть (C65)** | Группа `dharmashop`; Joomla Event, аргументы `cart`, `variant` (массив), `qty`, `params`, `error`. Плагин может изменить `qty` и `params` или задать `error` (языковой ключ) — позиция отклоняется. |
| `onDharmaShopCartAfterAdd` | **есть (C65)** | После добавления: `cart`, `variant`, `qty` (итоговое количество строки), `item_id`. |
| `onDharmaShopCartItemValidate` | **есть (C65)** | При чтении строк корзины: `cart`, `line` (объект строки), `error`; задайте `error`, чтобы строка стала недоступной и не вошла в итоги. |
| `onDharmaShopCartTotals` | **есть (C65)** | `cart`, `lines`, `subtotal`, `adjustments`; плагин дополняет `adjustments` строками `['title' => …, 'amount' => …]` (скидка — отрицательная сумма). |
| `Checkout…`, `Shipping…`, `Payment…`, прочие `Order…`, `Notify…` | план, этап 5 | кроме реализованных ниже событий C76 |
| `onDharmaShopOrderNotify` | **есть (C76)** | Группа `dharmashopnotify`; после commit создания/смены статуса и при ручном повторе ошибок. Аргументы `notification: Administrator\Notification\OrderNotification` (readonly снимок order/items/adjustments/contacts, historyId, reason created/status), `settings: Registry`, `retry: bool`. Плагин использует журнал доставки; ошибка канала не отменяет заказ. |
| `onDharmaShopOrderAfterCreate` | **есть (C76)** | Группа `dharmashop`, только после commit нового заказа, `order_id`, `history_id`; повтор checkout не вызывает. Ошибки обработчиков журналируются. |
| `onDharmaShopOrderStatusChanged` | **есть (C76)** | Группа `dharmashop`, после commit фактической смены; `order_id`, `history_id`, `previous_status_id`, `status_id`. Не вызывается при сохранении прежнего статуса. |
| `onDharmaShopPaymentStatusChanged` | **есть (C83)** | После изменения статуса оплаты: `order_id`, `method_id`, `previous`, `status`; группа `dharmashop`. |

### Теги писем C76

Четыре шаблона `plg_dharmashopnotify_email.order.new.admin/customer`, `.status.admin/customer` в штатных «Шаблонах писем». Теги: `{SITENAME}`, `{ORDER_NUMBER}`, `{ORDER_DATE}`, `{STATUS}`, `{STATUS_MESSAGE}`, `{TOTAL}`, `{ITEMS}`, `{CONTACTS}`, `{ORDER_LINK}`. Это теги Joomla MailTemplate, не HTML-шорткоды витрины. Данные — сохранённый снимок; внутренние комментарии в письмо не входят; с C77 согласия включаются отдельным блоком CONSENTS. Для администрации ссылка ведёт в админку, для покупателя с user_id — на собственный заказ; для гостя пустая. SMTP и отправитель берутся из Joomla.
### События макетов и SEO

| Событие | Статус | Примечание |
|---|---|---|
| `onDharmaShopLandingAfterSave` | **есть (C85, L6)** | Группа `dharmashop`; после сохранения посадочной страницы: `id`, `is_new`, `source` (`filter` или `manual`). Для карты сайта, внешних кэшей и т. п. |
| `onDharmaShopLandingAfterDelete` | **есть (C85, L6)** | После удаления страниц: `ids` (массив). |
| `onDharmaShopLandingChecked` | **есть (C85, L6)** | После проверки страниц (кнопка или задача планировщика), только если что-то изменилось: `newly_broken`, `recovered` (массивы номеров). |
| `onDharmaShopMetadataReady` | **есть (C28/C29)** | Группа `dharmashop`; Joomla Event с `page` (readonly `Site\Seo\PageMetadata`), `document` (`HtmlDocument`), `settings` (`Registry` настроек магазина), `content` (readonly `Site\Seo\CollectionMetadata`, `Site\Seo\ProductMetadata` или `null`). После базовых тегов, до рендера HTML; только сайт. Социальный плагин использует `page`, Schema.org — `page` и готовый `content`; сами плагины не запрашивают каталог. |
| `onDharmaShopCategoryBeforeTitle` | **есть (C31)** | До заголовка видов `categories`, `category`, `brands`, `brand`, `badges`, `badge`; различайте их по `view`. |
| `onDharmaShopCategoryAfterTitle` | **есть (C31)** | После заголовка этих шести видов. |
| `onDharmaShopCategoryBeforeContent` | **есть (C31)** | Перед содержимым этих видов: изображение, интро, список и описание. |
| `onDharmaShopCategoryAfterContent` | **есть (C31)** | После содержимого этих видов, например для связанных товаров. |
| `onDharmaShopProductBeforeTitle` | **есть** | До заголовка товара, в том числе при скрытом h1. |
| `onDharmaShopProductAfterTitle` | **есть** | После заголовка товара. |
| `onDharmaShopProductBeforeContent` | **есть** | Перед основным содержимым товара. |
| `onDharmaShopProductAfterContent` | **есть** | После основного содержимого товара. |
| `onDharmaShopProductBeforeGallery` / `onDharmaShopProductAfterGallery` | **есть (2026-10-08)** | Внутри колонки галереи, до/после её шаблона. |
| `onDharmaShopProductBeforeIntro` / `onDharmaShopProductAfterIntro` | **есть (2026-10-08)** | Внутри средней колонки, до/после краткого описания и выбранных характеристик. |
| `onDharmaShopProductBeforeIntroFields` / `onDharmaShopProductAfterIntroFields` | **есть (2026-10-08)** | В default_intro.php, до/после выбранных характеристик, в том числе при пустом наборе. |
| `onDharmaShopProductBeforeBuy` / `onDharmaShopProductAfterBuy` | **есть (2026-10-08)** | В правой колонке, до/после всей карточки покупки. |
| `onDharmaShopProductBeforePrice` / `onDharmaShopProductAfterPrice` | **есть (2026-10-08)** | В card-body покупки, до/после цены со скидкой/таймером; также при скрытой цене. |
| `onDharmaShopProductBeforeActions` / `onDharmaShopProductAfterActions` | **есть (2026-10-08)** | В card-body покупки, до/после блока действий; также в режиме каталога. |
| `onDharmaShopProductBeforeTabs` / `onDharmaShopProductAfterTabs` | **есть (2026-10-08)** | На всю ширину под тремя колонками, до/после набора вкладок; также если он пуст. |
| `onDharmaShopProductBeforeDescription` / `onDharmaShopProductAfterDescription` | **есть (2026-10-08)** | Внутри существующей вкладки «Описание», до/после fulltext. |
| `onDharmaShopProductTabs` | **есть (2026-10-08)** | Дополнительные панели после стандартных вкладок. `data.tabset` — ID уже инициализированного UITab. Работает и при отсутствии описания/групп. |
| `onDharmaShopProductGalleryActions` | **есть (C88)** | Дополнительные действия поверх галереи страницы товара; в `data` доступны `product` и `favorite`. |
| `onDharmaShopProductCardMediaActions` | **есть (C88)** | Дополнительные кнопки в области фото каждой карточки, в том числе на страницах модулей; в `data` доступны `product`, `favorite`, `productUrl`. Состояние избранного загружается пачкой, а не отдельным запросом на каждый товар. |
| `onDharmaShopProductCardActions` | **есть (C88)** | Дополнительные действия карточки рядом с покупкой; те же данные, что у слота фото. Для будущего сравнения используйте отдельную кнопку и отдельное состояние. |
| `onDharmaShopSettingsPrepareForm`, `AdminToolsCollect` | план | вкладки настроек и панель инструментов от плагинов |
| `onDharmaShopOrderRulesResolve` | **предложено** | итоговые правила оформления (количество, единица, учёт остатков) для товара: плагин может переопределить результат `Order\OrderRules` (C14), например, для оптовых групп |
| `onDharmaShopSeoPlaceholders` | **предложено** | плагин добавляет свои подстановки в `Placeholder\Renderer` (значения по области и имени) |
| `onDharmaShopImageStored` | **предложено** | после загрузки изображения (`ImageUploader::store`): плагин конвертирует в WebP, нарезает размеры (F10) |

### Контракт точек вывода C31

Группа плагинов `dharmashop`, событие `Site\Event\LayoutRenderEvent` (Joomla CMS immutable event + ResultAware, принимает только строки). Аргументы:

- `entity`: `category` / `product` / `productCard`. Бренды и бейджи используют общий `entity=category`.
- `view`: `categories` / `category` / `brands` / `brand` / `badges` / `badge` / `product` либо контекст карточки (`product.category`, `module.products` и др.).
- `position`: общие позиции `beforeTitle` / `afterTitle` / `beforeContent` / `afterContent`; у товара также `before/afterGallery`, `galleryActions`, `before/afterIntro`, `before/afterIntroFields`, `before/afterBuy`, `before/afterPrice`, `before/afterActions`, `before/afterTabs`, `before/afterDescription`, `tabs`; у карточки — `mediaActions` и `actions`.
- `item`: текущая проверенная моделью категория/товар; на корне списка категорий — виртуальная запись `id=0` из `root_category`, а не запись БД.
- `items`: записи текущего списка в порядке вывода; на отдельном товаре пустой массив.
- `params`: копия разрешённых параметров отображения (меню/категория/родители/магазин).
- `page`: `Site\Seo\PageMetadata` для SEO-контекста текущей страницы либо `null`.
- `data`: дополнительные данные позиции, по умолчанию `[]`. У ProductTabs — `tabset` (string); прежние аргументы и обработчики сохраняются.

Обработчик добавляет HTML через `$event->addResult($html)`. Обычный `return $html` диспетчер в результат не собирает. Прямой `echo` нарушает расположение блока; использовать только результат события. Строки объединяются без дополнительной обёртки и экранирования в порядке обработчиков Joomla; HTML, Bootstrap-классы и экранирование данных формирует сам плагин. Пустая строка/отсутствие результата ничего не выводит. Заменять аргументы события нельзя; контекст записи используется для чтения и построения своего блока, не для сохранения данных.

Точки вокруг заголовка вызываются даже при скрытом h1. Точки контента охватывают всё содержимое страницы после заголовка. События карточек вызываются для каждой карточки после подготовки общего состояния; обработчики не должны выполнять отдельные запросы на каждый товар. Вызов доступен на сайте в HTML-документе, включая карточки модулей вне `com_dharmashop`. На категории и товаре используются проверенные записи `item`; события карточек получают исходный товар в `item`. `Product\HtmlView::renderHook($position, $data = [])` доступен частичным шаблонам и переопределениям.

Пример методов плагина `CMSPlugin implements SubscriberInterface`:

```php
use Dharma\Component\DharmaShop\Site\Event\LayoutRenderEvent;

public static function getSubscribedEvents(): array
{
    return ['onDharmaShopCategoryAfterContent' => 'afterCategory'];
}

public function afterCategory(LayoutRenderEvent $event): void
{
    if ($event->getArgument('view') !== 'category') {
        return; // Оставить блок только на странице списка товаров категории.
    }
    $category = $event->getArgument('item');
    $title = htmlspecialchars((string) $category->title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $event->addResult('<aside class="mt-4"><h2 class="h5">Дополнительно: ' . $title . '</h2></aside>');
}
```

Это пример подключения, демонстрационный плагин не создавался. Для списка подкатегорий фильтруйте по `view=categories`.

### Добавление вкладки плагином товара

Методы плагина группы `dharmashop` (`CMSPlugin implements SubscriberInterface`):

```php
use Dharma\Component\DharmaShop\Site\Event\LayoutRenderEvent;
use Joomla\CMS\HTML\HTMLHelper;

public static function getSubscribedEvents(): array
{
    return ['onDharmaShopProductTabs' => 'productTabs'];
}

public function productTabs(LayoutRenderEvent $event): void
{
    $tabset = $event->getArgument('data')['tabset'];
    // Уникальный суффикс своего плагина; название из языкового файла экранировать
    // для innerHTML компонента Joomla, как в product/default_tabs.php.
    $html = HTMLHelper::_('uitab.addTab', $tabset, $tabset . '-myplugin-info', 'Информация');
    $html .= '<div class="p-3">Дополнительный блок магазина.</div>';
    $html .= HTMLHelper::_('uitab.endTab');
    $event->addResult($html);
}
```

Плагин добавляет только панели, не открывает/закрывает весь UITab. Встроенные описание и группы идут первыми, затем панели плагинов в порядке Joomla. Если встроенных вкладок нет, компонент Joomla активирует первую панель плагина. Событие вызывается один раз: его HTML собирается после инициализации UITab и вставляется после стандартных панелей; при полностью пустом результате весь блок не выводится. Инициализация подключает штатные ресурсы UITab даже на пустом наборе. Before/AfterTabs — обычные блоки вне набора, Before/AfterDescription — внутри существующего описания.

Для добавления блока, например под покупкой, подписаться на `onDharmaShopProductAfterBuy` и передать HTML через `addResult`. Переопределения должны сохранять вызовы renderHook в нужных местах; удаление вызова удаляет соответствующую точку расширения. Пример — документация, плагин не установлен.

## Реализация в коде

- Реестры типов: `com_dharmashop/admin/src/{Fields,Shipping,Payment}/TypeRegistry.php`.
- Корзина: `com_dharmashop/admin/src/Cart/CartService.php`; создание заказов и статусы: `OrderService`, `OrderStatusService`, `NotificationService`.
- Метаданные: `com_dharmashop/site/src/Seo/PageSeo.php`; макетные события: `site/src/Support/LayoutHooks.php`, `site/src/Event/LayoutRenderEvent.php`.
- Плагины-подписчики и пользовательское назначение перечислены в [HELP.md](HELP.md); общие правила расширения — в [DEVELOPER_GUIDE.md](DEVELOPER_GUIDE.md).
