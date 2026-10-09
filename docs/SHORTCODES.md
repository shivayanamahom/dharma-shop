# Реестр: подстановки, шорткоды, триггеры

Единое место, где перечислено всё, что другие части системы (шаблоны SEO, письма, тексты материалов, плагины) могут подставлять или перехватывать. **Появилась новая подстановка, шорткод или событие — сначала запись сюда** (и в `ARCHITECTURE.md` §6 для событий), потом код. Статусы: **есть** — работает в коде; **план** — имя зарезервировано, реализация на указанном этапе; **идея** — не решено, обсуждаем.

Имена только наши: подстановки `{область.имя}`, шорткоды `{dharma-…}`, события `onDharmaShop…`.

**Обновление 2026-10-07:** `Site\Placeholder\Renderer` работает в SEO-полях, интро и описаниях страниц списка категорий и товаров категории. Общие `global.sitename`, `global.currency`, `global.url` и значения категории подключены; цены и счётчик ленивые. В HTML текстах сохраняется разметка, подставленные значения экранируются, неизвестный код остаётся как есть.

## 1. Подстановки в шаблонах `{область.имя}`

Используются там, где пользователь задаёт шаблон текста: SEO-шаблоны страниц (настройки → «SEO», C16), позже письма, названия позиций в заказе, тексты уведомлений. Правила: фигурные скобки, латиница, точка между областью и именем; неизвестная подстановка выводится как есть (не ломает страницу); значения экранируются при выводе; один движок подстановки на всех (класс `Placeholder\Renderer`, этап «сайт»; пока шаблоны только хранятся и показываются в подсказках).

### Общие (`global`)

| Подстановка | Значение | Статус |
|---|---|---|
| `{global.sitename}` | название сайта (настройки Joomla) | SEO и тексты category/categories |
| `{global.currency}` | символ валюты магазина | SEO и тексты category/categories |
| `{global.url}` | абсолютный URL сайта | SEO и тексты category/categories |

### Категория, бренд, бейдж

| Подстановка | Значение | Статус |
|---|---|---|
| `{category.title}` / `{brand.title}` / `{badge.title}` | название записи | category/categories SEO и тексты; brand/badge — SEO-шаблоны, страницы ещё не реализованы |
| `{category.parent}` | название родительской категории (пусто у корневой) | SEO, интро и описание category/categories |
| `{category.price_min}` / `{category.price_max}` | минимальная и максимальная цена опубликованных товаров категории с вложенными, форматируется с валютой | category/categories SEO, интро и описание; ленивый индексный запрос. На виртуальном корне цены пока пусты; см. ARCHITECTURE §5.17 |

Замена нулевой цены (C67): при включённой настройке «Заменять нулевую цену текстом» подстановки `{product.price}`, `{category.price_min}`, `{category.price_max}` с нулевым значением выводят заданный текст (по умолчанию «Цена по запросу») вместо «0 ₽».
| `{category.count}` | число товаров категории с вложенными; корень считает каталог; потолок 10 000 (`10000+`) | category/categories SEO, интро и описание; вычисляется только при использовании |
| `{category.introtext}` | краткое описание без HTML, до 200 символов | category/categories SEO, интро и описание |

Код подстановки в окне справочника админки лежит в поле ввода (копируется чистым текстом, без форматирования); если подстановка всё же вставлена в редактор с оболочкой `<code>`/`<kbd>`/`<samp>`, движок снимает её вокруг одиночного кода и выводит обычный текст.

В общих настройках вкладки SEO справочник находится справа от полей (xl: настройки 8/12, справочник 4/12; на меньших экранах — под полями). Поле `ShortcodesField` (`_seo_shortcodes`, `filter="unset"`) использует общий `PlaceholderCatalog`: все области (категория/бренд/бейдж/товар) и общие коды без повторов, только название и код для копирования, без колонки «Сейчас» и без запросов к записям. Группы — нативные `<details open>`, можно свернуть. Раскладку только этой вкладки задаёт существующий settings-search.js Bootstrap-классами, перемещая прежние узлы полей; поиск/showon/сохранение не меняются. Без JavaScript справочник доступен перед полями. В товарах/категориях прежний PlaceholderPanel с колонкой «Сейчас». Код нужной области используется в соответствующем шаблоне SEO. Новых подстановок нет.

В интро и полном описании category/categories можно написать, например, «Столы от {category.price_min}». Подстановка не разбирает HTML как шаблон: разметка (теги) не затрагивается, значения экранируются и не интерпретируются повторно; правило пустых значений (ниже) действует и в текстовых узлах.

На **странице корня раздела** (C17, ARCHITECTURE §5.18) `{category.title}` — заголовок виртуального корня («Каталог»), `{category.count}` считает товары; глобальные коды доступны. Цены по всему корню пока не считаются.

Пример SEO-шаблона категории «Синие столы»: `{category.title} — цены от {category.price_min} до {category.price_max} | {global.sitename}`. Диапазон считается по товарам самой категории.

**Пустые значения (реализовано, `Site\Placeholder\Renderer`):** если у подстановки нет значения (в категории нет товаров с ценой), из текста вырезается весь фрагмент между ближайшими знаками препинания (`, ; . | — – : ( ) ! ?`) вместе с соседним знаком, а не остаётся «цены от до»: «Столы — цены от {min} до {max} | Сайт» без цен даёт «Столы | Сайт»; пустое содержимое скобок убирает скобки целиком. Неизвестный код остаётся как есть (опечатка видна). Значения вычисляются лениво — запрос выполняется, только если код есть в шаблоне. Если шаблон после подстановки пуст, берётся следующий: запись → пункт меню → настройки → встроенный.

### Товар

| Подстановка | Значение | Статус |
|---|---|---|
| `{product.title}` | название | есть в шаблонах SEO |
| `{product.sku}` | артикул основного варианта | SEO/тексты товара и карточек списка, предпросмотр |
| `{product.category}` | основная категория | SEO/тексты товара и карточек списка, предпросмотр |
| `{product.brand}` | опубликованный доступный бренд | SEO/тексты товара и карточек списка, предпросмотр |
| `{product.price}` | цена витрины с учётом скидки и замены нуля; диапазон, если варианты различаются | SEO/тексты товара и карточек списка, предпросмотр |
| `{product.price_old}` | старая цена основного варианта при действующей скидке | SEO/тексты товара и карточек списка, предпросмотр |
| `{product.discount}` | размер действующей скидки основного варианта, в процентах | SEO/тексты товара и карточек списка, предпросмотр |
| `{product.price_min}` / `{product.price_max}` | прежние алиасы границ цены для совместимости сохранённых текстов | скрыты из справочника; для новых текстов используйте `{product.price}` |
| `{product.unit}` | единица измерения товара | SEO/тексты товара и карточек списка, предпросмотр |
| `{product.stock}` | наличие: «в наличии» / «нет в наличии» | SEO/тексты товара и карточек списка, предпросмотр |
| `{product.introtext}` | краткое описание без HTML/сущностей, пробелы нормализованы, до 200 символов (длиннее — многоточие) | SEO записи/пункта меню/общих настроек; тексты карточек списка и страницы товара; справочник товара с предпросмотром и сайдбар SEO настроек |
| `{field.<alias>}` | значение поля товара (этап 2) | план |

`ProductValues::introtext()` — единая подготовка текста для витрины и предпросмотра в админке, без запросов к БД. Подстановка однократная: значение не разбирается повторно как шаблон, поэтому вставлять `{product.introtext}` в само исходное интро не следует. Страница товара выводит интро через `Product\HtmlView::text()` (`Renderer::renderHtml`); тот же метод доступен в переопределениях для полного описания. HTML исходного редактора сохраняется, значения шорткодов экранируются; новые поля/настройки не требуются.

### Заказ (письма и уведомления, этап 5)

`{order.number}`, `{order.total}`, `{order.status}`, `{order.date}`, `{order.items}` (список позиций), `{customer.name}`, `{customer.email}`, `{customer.phone}`, `{shipping.method}`, `{shipping.price}`, `{payment.method}`, `{payment.link}` — **план**, состав уточним на этапе 5 вместе со структурой писем (ARCHITECTURE §8).

## 2. Шорткоды в тексте материалов

Для вставки данных магазина в обычный текст (описание категории, статья Joomla, модуль). Предложение — отдельный контент-плагин `plg_content_dharmashop`; в ядро не входит (правило B: нужное не каждому — в плагины).

| Шорткод | Что выводит | Статус |
|---|---|---|
| `{dharma-price id="12"}` | цена товара (или `sku="TBL-100"`) | идея |
| `{dharma-product id="12"}` | плитка товара | идея |
| `{dharma-products category="5" limit="8" order="new"}` | список товаров | идея (есть модули витрины E14) |
| `{dharma-buy id="12"}` | кнопка «В корзину» | идея, этап 5 |
| `{dharma-stock id="12"}` | наличие | идея |

Формат: атрибуты в двойных кавычках; без закрывающего тега; ошибка (нет товара) — пустой вывод и запись в журнал. Нужно ли это ядру и где проходит граница с подстановками — решим на этапе «сайт» (вопрос пользователю).

## 3. Триггеры (события)

Полный список с аргументами — **`ARCHITECTURE.md` §6** (каталог, поля, корзина, заказ, служебные). Здесь — только то, что добавилось или уточнилось, и текущий статус.

| Событие | Статус | Примечание |
|---|---|---|
| `onDharmaShopProductBeforeSave` / `AfterSave`, `VariantBeforeSave` / `AfterSave`, `ProductBeforeDelete` / `AfterDelete` | план (этап 1, не подключены) | сейчас сохранение идёт без вызова событий; подключить вместе с первым плагином |
| `onDharmaShopProductPrepareForm` | план | форма товара собирается в `ProductModel::preprocessForm` |
| `onDharmaShopListingQuery`, `ProductPrepare`, `ProductDisplay`, `PriceResolve`, `StockChange`, `IndexRebuild` | план | витрина и индекс |
| `onDharmaShopFieldTypesCollect` (группа `dharmashopfield`) | **есть (C53/C59)** | Аргумент `registry: Administrator\Fields\TypeRegistry`; провайдер добавляет `TypeInterface` через `register()`. Для ввода/проверки значений также реализует `ValueTypeInterface` (`input/defaultValue/decode/encode`), без новых событий и SQL в провайдере. Дубликаты ID запрещены, после dispatch реестр закрыт. |
| `onDharmaShopPaymentTypesCollect` (группа `dharmashoppayment`) | **есть (C83)** | Аргумент `registry: Administrator\Payment\TypeRegistry`; плагин регистрирует `Payment\TypeInterface` (id = element плагина). Контракт и примеры — `docs/PAYMENTS.md`. |
| `onDharmaShopPaymentStatusChanged` (группа `dharmashop`) | **есть (C83)** | После изменения статуса оплаты заказа (`PaymentService::apply`): `order_id`, `method_id`, `previous`, `status` (pending/paid/failed/cancelled). Применение: интеграции, уведомления. |
| `onDharmaShopShippingTypesCollect` (группа `dharmashopshipping`) | **есть (C80)** | Аргумент `registry: Administrator\Shipping\TypeRegistry`; плагин регистрирует `TypeInterface` (id = element плагина). Правила — `docs/SHIPPING.md`. |
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

### Подстановки писем C76

Четыре шаблона `plg_dharmashopnotify_email.order.new.admin/customer`, `.status.admin/customer` в штатных «Шаблонах писем». Теги: `{SITENAME}`, `{ORDER_NUMBER}`, `{ORDER_DATE}`, `{STATUS}`, `{STATUS_MESSAGE}`, `{TOTAL}`, `{ITEMS}`, `{CONTACTS}`, `{ORDER_LINK}`. Это теги Joomla MailTemplate, не HTML-шорткоды витрины. Данные — сохранённый снимок; внутренние комментарии в письмо не входят; с C77 согласия включаются отдельным блоком CONSENTS. Для администрации ссылка ведёт в админку, для покупателя с user_id — на собственный заказ; для гостя пустая. SMTP и отправитель берутся из Joomla.
| `onDharmaShopMetadataReady` | **есть (C28/C29)** | Группа `dharmashop`; событие Joomla Event с `page` (readonly `Site\Seo\PageMetadata`), `document` (`HtmlDocument`), `settings` (`Registry` настроек магазина), `content` (readonly `Site\Seo\CollectionMetadata` или `null`). `content`: название списка, упорядоченные `items` с `name`/абсолютным `url`, тип элемента (на категории `CollectionPage`). После применения базовых тегов, до рендера страницы; только сайт/HTML. Социальный плагин использует `page`, плагин Schema.org — также `content`, без запросов каталога. |
| `onDharmaShopCategoryBeforeTitle` | **есть (C31)** | До заголовка страницы категории; общий для `categories` и будущего `category`, различать по `view`. |
| `onDharmaShopCategoryAfterTitle` | **есть (C31)** | После заголовка страницы категории. |
| `onDharmaShopCategoryBeforeContent` | **есть (C31)** | Перед содержимым категории: изображение, интро, список и полное описание. |
| `onDharmaShopCategoryAfterContent` | **есть (C31)** | После всего содержимого категории, например для связанных товаров. |
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
| `onDharmaShopSettingsPrepareForm`, `AdminToolsCollect` | план | вкладки настроек и панель инструментов от плагинов |
| `onDharmaShopOrderRulesResolve` | **предложено** | итоговые правила оформления (количество, единица, учёт остатков) для товара: плагин может переопределить результат `Order\OrderRules` (C14), например, для оптовых групп |
| `onDharmaShopSeoPlaceholders` | **предложено** | плагин добавляет свои подстановки в `Placeholder\Renderer` (значения по области и имени) |
| `onDharmaShopImageStored` | **предложено** | после загрузки изображения (`ImageUploader::store`): плагин конвертирует в WebP, нарезает размеры (F10) |

### Контракт точек вывода C31

Группа плагинов `dharmashop`, событие `Site\Event\LayoutRenderEvent` (Joomla CMS immutable event + ResultAware, принимает только строки). Аргументы:

- `entity`: `category` / `product`.
- `view`: `categories` / `category` / `product`; `categories` — список дочерних категорий, `category` — будущий список товаров выбранной категории.
- `position`: четыре общие позиции `beforeTitle` / `afterTitle` / `beforeContent` / `afterContent`; у товара также `before/afterGallery`, `before/afterIntro`, `before/afterIntroFields`, `before/afterBuy`, `before/afterPrice`, `before/afterActions`, `before/afterTabs`, `before/afterDescription`, `tabs` (регистр как в LayoutHooks).
- `item`: текущая проверенная моделью категория/товар; на корне списка категорий — виртуальная запись `id=0` из `root_category`, а не запись БД.
- `items`: записи текущего списка в порядке вывода; на отдельном товаре пустой массив.
- `params`: копия разрешённых параметров отображения (меню/категория/родители/магазин).
- `page`: готовый `PageMetadata` или `null` в будущих видах до подключения SEO.
- `data`: дополнительные данные позиции, по умолчанию `[]`. У ProductTabs — `tabset` (string); прежние аргументы и обработчики сохраняются.

Обработчик добавляет HTML через `$event->addResult($html)`. Обычный `return $html` диспетчер в результат не собирает. Прямой `echo` нарушает расположение блока; использовать только результат события. Строки объединяются без дополнительной обёртки и экранирования в порядке обработчиков Joomla; HTML, Bootstrap-классы и экранирование данных формирует сам плагин. Пустая строка/отсутствие результата ничего не выводит. Заменять аргументы события нельзя; контекст записи используется для чтения и построения своего блока, не для сохранения данных.

Точки вокруг заголовка вызываются даже при скрытом h1. Точки контента охватывают всё содержимое страницы после заголовка. События не вызываются внутри каждой карточки списка. Только сайт/HTML/com_dharmashop, без запросов БД в механизме вызова. На категории и товаре вызовы используют реальную проверенную запись `item`. `Product\HtmlView::renderHook($position, $data = [])` доступен частичным шаблонам и переопределениям; общая категория продолжает пользоваться LayoutHooks напрямую.

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
        return; // Этот пример предназначен для будущего списка товаров категории.
    }
    $category = $event->getArgument('item');
    $title = htmlspecialchars((string) $category->title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $event->addResult('<aside class="mt-4"><h2 class="h5">Дополнительно: ' . $title . '</h2></aside>');
}
```

Это пример подключения, демонстрационный плагин не создавался. Для работающего сейчас списка подкатегорий фильтровать по `view=categories`.

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

## 4. Где в коде

- Шаблоны SEO и их значения по умолчанию: `admin/src/Seo/SeoSettings.php`, подсказки — `SeoHints.php`; настройки — `admin/config.xml`, вкладка `seo`.
- Правила оформления заказа: `admin/src/Order/OrderRules.php`.
- Точки макетов: `site/src/Support/LayoutHooks.php`, `site/src/Event/LayoutRenderEvent.php`; вызовы — `site/tmpl/categories/default.php`, `category/default.php`, `product/default.php`. Социальный и Schema.org плагины уже входят в пакет; остальные события — по `ARCHITECTURE.md` §6.

### Дополнение C77: оформление email

Уточнение логотипа: `SHOP_LOGO` в текстовых данных остаётся абсолютным URL; в HTML при встраивании локального изображения становится `cid:dharma-shop-logo@dharmashop`, пригодным для src. MIME inline-файл добавляет отправитель. Внешние URL не скачиваются.

Имена в MailTemplate — в верхнем регистре. `SITENAME` для обратной совместимости теперь равно имени магазина (`shop_name`, иначе название Joomla). Новые скалярные теги: `SHOP_NAME`, `SHOP_EMAIL`, `SHOP_PHONE`, `SHOP_ADDRESS`, `SHOP_URL`, `SHOP_LOGO` (абсолютный HTTP(S) URL), `SHOP_CONTACTS` (общая контактная подпись), `SHIPPING_TITLE`, `PAYMENT_TITLE`. Пользовательские строки экранируются в HTML.

`ORDER_DETAILS` — блок номера/даты/статуса; `SHIPPING`, `PAYMENT` — условные блоки названий способов из снимка. В HTML-версии `ITEMS` — таблица, `CONTACTS` — контактный блок, `STATUS_MESSAGE` — сообщение, `ORDER_LINK` — кнопка. В текстовой версии те же имена содержат обычный текст/URL. Пустой выключенный блок даёт пустую строку. Гостевых постоянных ссылок по-прежнему нет. Оболочка `plg_dharmashopnotify_email/tmpl/mail.php` добавляет шапку с названием/логотипом и контактную подпись, не зависит от глобального mail_style Joomla. Тема и обе версии содержимого редактируются в штатных шаблонах писем.

2026-10-08: HTML ITEMS включает фото 64 px и ссылку на товар в заголовке. В notification.items дополнительно передаются product_id из позиции и available_product_id/cover из текущего опубликованного товара (LEFT JOIN, отсутствующий товар даёт NULL). Название/артикул/количество/цены остаются из снимка. Новых тегов нет; текстовая версия ITEMS прежняя. Локальные изображения письма встраиваются через CID, внешние URL не скачиваются. Подробнее NOTIFICATIONS.md.

Уточнение C77: `CONSENTS` — отдельный блок фактических согласий из `contacts.consents`, для администрации и покупателя независимо от CONTACTS. Текст и link_label, безопасная ссылка HTTP(S), отметка Да/Нет, accepted_at при наличии. Неотмеченное необязательное согласие явно показывает Нет; отсутствующие старые сведения не выдумываются. Никаких новых отметок при отправке не создаётся.

### Событие браузера при изменении корзины

`dharmashop:cart-updated` — существующее DOM-событие на `document`. В `cart.js` detail содержит прежнюю сводку `cart` (`count`, `qty`, `subtotal`, `subtotalText`, `url`, `products`) и `source` (`add` — успешное добавление, `change` — изменение/удаление, `error` — отклонённое действие). Добавлено необязательное `notification: null | {type: 'toast', html: string}`: серверный экранированный макет только после успешного `cart.add`, если выбран toast. У других отправителей события notification может отсутствовать. `cart-toast.js` обрабатывает это поле; модуль и виджеты продолжают читать прежние поля. Настройки вида/таймера и контракт макета — LAYOUTS.md.

Обновление 2026-10-08: все коды справочника товара подключены в ProductValues; price_min/max скрыты, прежние сохранённые тексты совместимы. `onDharmaShopMetadataReady.content` также принимает ProductMetadata (name/sku/brand/description/image/price/currency/inStock); числовая цена без форматирования либо null. Списки товаров передают CollectionMetadata с itemType=Product. Плагины каталог не читают.

ProductMetadata.inStock (2026-10-08): фактическая доступность выбранного SKU по учёту остатков, а не общий флаг разрешения заказа. Не меняет подстановку product.stock, использующую индекс витрины. Правило и backorder — SEO.md.

### Потребитель событий товара: связанные товары

`plg_system_dharmashoprelated` использует существующие `onDharmaShopProductBefore/After…` и `onDharmaShopProductTabs`; список мест показа соответствует `LayoutHooks::EVENTS['product']`. В `Tabs` передаётся существующий `data.tabset`; плагин добавляет панель нативного UITab. Новые шорткоды и события не вводятся. Настройки, ручной пикер и переопределения — RELATED_PRODUCTS.md.
