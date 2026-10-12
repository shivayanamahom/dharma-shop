# Dharma Shop — архитектура

## Фильтр F4–F8 (2026-10-12)

При выборе представителя группы значение сортировки вычисляется в запросе кандидатов и передаётся через derived table как `listing_ranked.listing_sort`. Внешний запрос сортирует по этому значению: псевдоним поискового подзапроса `m` доступен только внутри. Один рейтинг определяет и представителя, и положение группы в поисковой выдаче.

Текущий контракт — FILTER_IMPLEMENTATION.md. Optional Fields\FilterTypeInterface возвращает filterKind option/number/range/null, согласованный со store(); провайдеры не передают SQL. FilterRequest проверяет модуль-инициатор и переопределяет логику поля на сервере. FilterState ограничивает количество полей/значений и конечные диапазоны ±1e12.

ScopedFacets использует запрос CategoryModel, DISTINCT unit (товар/группа) и ограниченный live-бюджет. Предрасчёт применяется только при доказанной совместимости состава/доступа, остальные случаи считаются по фактическим кандидатам. GroupListing выбирает первого подходящего участника оконной функцией до LIMIT, затем обогащает группы страницы пакетно. Полные группы и все карточки вариантов в HTML не загружаются. Materialized products.on_sale/idx_sale сопровождается пересчётом цен и планировщиком акций; FilterStorage добавляет схему аддитивно. FacetIndexer порционно/транзакционно сопровождает derived данные; кэш инвалидируется каталогом.

CatalogModules и system/dharmashoppositions дают три внутренние позиции, соблюдая native menu/ACL. FilterController::refresh — read-only GET формы для button-mode каскада, no-store/noindex, без карточек. AJAX списка сохраняет обычный серверный HTML и историю. Локальные ресурсы подключаются только по функциям; исключение CSS ограничено геометрией range. Фактические пределы скорости, включая недостигнутую цель для большой групповой категории, — BENCHMARKS_NOTES.md.

**Индекс выдачи групп (C89).** Таблицы `#__dharmashop_group_listing (catid, group_id, sort_order, price_min, price_max, created, title_sort, in_stock)` — строка группы в каждой категории показа (0 — весь каталог) с индексами сортировки, `#__dharmashop_group_keys (group_id, kind, ref, opt)` — значения полей (kind 1), бренды (2), бейджи (3) и категории (4) участников, `#__dharmashop_group_bounds (group_id, ref, num_min, num_max)` — границы чисел (ref — поле, 0 — цена). Строит и сопровождает `Catalog\GroupIndex`: вызывается из `GroupService::rebuild()/rebuildMany()` (изменение участников, состава, цены, наличия, публикации), `ProductgroupModel::save/publish`, `ProductgroupTable::delete`, `Indexer::setCategories/setCategoryOrder/rebuildCategorySubtree`, публикации и переноса категории, команды «Пересобрать витринный индекс», установки; `verify()` и задача планировщика проверяют расхождения. Читает `Site\Support\IndexedListing`: нефильтрованный список (две индексированные ветки с `LIMIT смещение+страница` и слиянием), total и базовые счётчики групп (`facets()`), а `GroupListing::applyContext(..., $indexed)` заменяет коррелированные проверки видимости и состава категории поиском по `group_listing` в выдаче с фильтром. Индекс не содержит доступа посетителя: применяется, только если `GroupIndex::accessible($levels)` (все опубликованные категории, бренды и бейджи доступны уровням просмотра) и `ready()`.

Правки по ревью 2026-10-12 (подробно FILTER_IMPLEMENTATION.md, `docs/REVIEW_CLAUDE.md`): `FilterCache` — один расчёт на ключ (`GET_LOCK`); нефильтрованный total и порядок первых 5000 единиц выдачи групп кэшируются до записи каталога; список и total используют `QueryBudget::hard()` (10 с) вместо узкого бюджета фасетов; `GroupListing::applyContext($materialize)` считает видимость групп и состав категории один раз для выдачи без выборочного фильтра; границы чисел `filter_ranges` пересчитываются только когда товар был краевым значением; поиск коротких слов идёт по `search.idx_title`.

Статус: первоначальная модель утверждена 2026-10-06; 2026-10-09 C87 заменяет её самостоятельными товарами и группами выбора (VARIANTS.md §12–14). Схема БД реализуется поэтапно (раздел 10). Если реализация расходится с документом, правится документ.

Для тех, кто продолжает работу (человек или ИИ): сначала `START_HERE.md` (исходное задание), затем `DECISIONS.md`, затем этот файл.

## 1. Принципы

1. **Нативно Joomla 6** (PHP ≥ 8.3, MySQL 8+/MariaDB 10.6+): пространства имён, сервис-провайдеры, `SubscriberInterface`, Web Asset Manager, `RouterView`, права через `access.xml`, консольные плагины, планировщик задач, шаблоны писем.
2. **Нативная админка:** формы Form XML (`subform`, `showon`, `uitab`, `searchtools`), штатный тулбар и сообщения. Одобренные исключения: загрузчик изображений C13 и поиск по настройкам C72 (только JS, без CSS).
3. **Масштаб с первого дня:** любой запрос витрины идёт по индексу, без `JSON_*`, без `LIKE '%…%'`, без больших `OFFSET`. Критерии — раздел 9.
4. **Ядро маленькое:** то, что нужно не каждому магазину, — плагин. Ядро даёт события и бандлит минимум, чтобы магазин работал «из коробки».
5. **Чистая комната:** своя модель, свои таблицы, свои имена. Пользователь разрешил читать RadicalMart как справку; код, структуру и имена не копируем.
6. **Одноязычный магазин** (C2): в схеме нет столбца `language`; переводится интерфейс.
7. **Деньги — `DECIMAL`, в PHP строками** (без `float`); все суммы в одной валюте строки прайс-листа.

## 2. Модель предметной области

```
Категория (штатная Joomla)
   └── Товар-исполнение — собственные URL, SEO, поля, цена и остаток
          └── Ровно один служебный SKU (существующая таблица variants)
Группа товаров — необязательная связь обычных товаров через поля выбора
Поле — типизированный блок данных товара или категории
Корзина → Заказ (позиции ссылаются на служебный variant_id; ID сохраняются)
```

- **Типы категорий (2026-10-06, решение пользователя).** «Категория» бывает трёх типов: **категория** (дерево, адрес товара, основная и дополнительные), **бренд** (у товара один) и **бейдж** (плашка на карточке — «Хит», «Новинка», «Акция»; у товара несколько). Технически это три **секции** штатных категорий Joomla: `com_dharmashop`, `com_dharmashop.brand`, `com_dharmashop.badge` — у каждой свой раздел в менеджере категорий и свои дополнительные поля формы.
- **Каждое исполнение — обычный товар**, у него ровно один служебный SKU (`variants_mode = 1`, `UNIQUE variants(product_id)`). Цена и склад остаются в существующих таблицах; артикул может быть пустым у обычного товара.
- **Группа товаров** имеет 1–6 одиночных полей выбора типа list/color с флагом вариативности; каждый участник заполняет все поля, полное сочетание уникально. Одна группа на товар, до 500 участников; раздел админки «Группы товаров».
- В корзину и заказ всегда попадает SKU конкретного самостоятельного товара. Селектор открывает его стабильный URL. Выдача одной карточки, счётчики и SEO групп — отдельный этап Claude; пока списки показывают все товары.
- «Поле» — общее название для любого типизированного блока данных: короткий текст, число, список, мультивыбор, да/нет, **галерея**, файлы, видео-ссылка, таблица характеристик, связанные товары и т. д. Что именно умеет тип, определяет он сам (форма редактирования, способ хранения, фильтр, вывод); типы расширяются плагинами.

## 3. Имена

| Что | Имя |
|---|---|
| Пакет | `pkg_dharmashop` |
| Компонент | `com_dharmashop` |
| Пространство имён компонента | `Dharma\Component\DharmaShop\{Administrator,Site,Api}` (общий код сайта и админки — в `Administrator\`, как в штатных компонентах) |
| Таблицы | `#__dharmashop_*` |
| События | `onDharmaShop*` (классы — `Dharma\Component\DharmaShop\Administrator\Event\…`) |
| Группы плагинов | `dharmashop` (интеграции), `dharmashopfield`, `dharmashopshipping`, `dharmashoppayment`, `dharmashopnotify` |
| Пространство имён плагинов | `Dharma\Plugin\DharmaShopShipping\Standard` и т. п. |
| Модули | `mod_dharmashop_categories`, `_search`, `_filter` (архитектура и план — `FILTER.md`, C84), `_cart`, `_favorites` (C88), `_products` |
| Языковые ключи | `COM_DHARMASHOP_*`, `PLG_DHARMASHOPSHIPPING_STANDARD_*` |
| Права | `dharmashop.*` в `access.xml` |

Ловушка Joomla 6: `$option` выводится из пространства имён — в `HtmlView`/контроллерах задаём `protected $option = 'com_dharmashop'`. У `HtmlView` в 6.1 нет `getApplication()`.

## 4. Структура репозитория и пакета

```
pkg_dharmashop.xml                манифест пакета (вложенные zip, иначе Joomla не определяет тип package)
script.php                        проверка PHP/Joomla/БД, включение плагинов после установки
build.sh                          сборка dist/pkg_dharmashop-<версия>.zip
README.md
docs/                             DECISIONS.md, ARCHITECTURE.md, DEVELOPMENT.md
dev/                              скрипты стенда (не входят в пакет): boot.php, install.php, sync.sh, db.sh, web-login.sh, bench/
files_dharmashop_layouts/          нативное файловое расширение: общая библиотека JLayout (C32)
  files_dharmashop_layouts.xml     fileset target layouts/components/dharmashop/<slot>
  layouts/                        category-card, product-card, brand-card, badge-card
  language/ru-RU, en-GB
com_dharmashop/
  dharmashop.xml                  манифест компонента (меню админки, схема SQL, файлы)
  admin/
    access.xml  config.xml
    forms/                        Form XML: product, variant, field, fieldgroup, status, shipping, payment, order, customer, filter_*
    services/provider.php
    sql/install.mysql.utf8.sql  sql/uninstall.mysql.utf8.sql  sql/updates/mysql/<версия>.sql
    src/
      Controller/ Model/ Table/ View/ Field/ Extension/ Service/ Helper/
      Event/                      типизированные события (раздел 5)
      Catalog/                    ListingQuery, FilterBuilder, Indexer (витринный индекс), ProductRebuilder, SearchIndexer
      Fields/                     FieldTypeRegistry, встроенные типы, FieldValueStore
      Pricing/                    PriceResolver, SaleScheduler
      Stock/                      StockService (уровни, резервы, журнал движений)
      Cart/ Order/ Shipping/ Payment/ Mail/
      Console/                    команды: reindex, rebuild-products, recalc-prices, cleanup-carts
    tmpl/                         products, product, fields, field, fieldgroups, fieldgroup, ordering, orders, order, carts, cart, customers, customer, statuses, status, shippings, shipping, payments, payment
    language/ru-RU, en-GB
  site/
    src/ (Controller, Model, View, Service/Router)
    tmpl/ (categories, category, product, search, cart, checkout, order, orders)
    forms/ (checkout, filter)
  api/                            веб-сервисы Joomla — позже
  media/                          joomla.asset.json, административный uploader.css, минимум js; фронтенд — классы Bootstrap без своего CSS
plg_system_dharmashop/            слияние корзины при входе, хуки жизненного цикла
plg_system_dharmashopmenu/        раздел «Dharma Shop» в боковом меню админки после «Компонентов» (onPreprocessMenuItems), без записей в БД
plg_task_dharmashop/              задачи планировщика Joomla (реализовано): «скидки по датам» (`SaleScheduler::run`) и «сверка счётчиков фильтра» (`FacetIndexer::verify` + пересборка); очистка корзин — план
plg_dharmashop_imageresize/       C79: размеры по контекстам, производные Joomla Image/GD, кеш с двумя уровнями хеша
plg_dharmashopshipping_standard/  доставка (обычная): цена, бесплатно от суммы, конструктор полей покупателя (C80)
plg_dharmashopshipping_courier/   курьер: доставка до двери, поля адреса, цена и «бесплатно от суммы» (C80)
plg_dharmashopshipping_pickup/    самовывоз: пункт выдачи, контакты, часы работы, карта (C80)
plg_dharmashoppayment_offline/    «при получении / по счёту» (без онлайн-платежа)
mod_dharmashop_*/                 модули
```

Отдельно от базового пакета: `plg_console_dharmashopmigrate` (перенос с RadicalMart), `plg_dharmashop_request` (заявка), `plg_dharmashopnotify_telegram`, `plg_finder_dharmashop`, типы полей, службы доставки и оплаты, экспорт/импорт, аналитика.

**JLayout C32:** страницы остаются в `components/com_dharmashop/tmpl/<view>/`, подключаемые карточки устанавливаются файловым расширением в `layouts/components/dharmashop/<slot>/`. ID `components.dharmashop.<slot>.<file>`; FileLayout использует `component=none`, `client=0`, basePath=null, чтобы `templates/<template>/html/layouts/components/dharmashop/` и родительский шаблон имели приоритет. `LayoutField` сканирует установленный слот относительно JPATH_ROOT, сохранённое значение — имя без `.php`.

Пакет сначала устанавливает `files_dharmashop_layouts`, затем компонент и плагины. При переходе со старой папки `components/com_dharmashop/layouts` пакетный preflight сохраняет её файлы до автоматического удаления Joomla по новому манифесту; postflight восстанавливает их в общей библиотеке. Уже существующие общие файлы имеют приоритет, конфликтные версии остаются в снимке. Старые переопределения шаблонов копируются в новый путь без перезаписи и удаления исходников. Следующие обновления штатные; дополнительных SQL/изменений схемы нет. Полные пути и правила восстановления — LAYOUTS.md §C32.

Уроки из прошлых проектов: пакет только из вложенных zip; пустые папки в манифесте ломают установку; Apache (Kettle) перезапускать после нового виртуального хоста; в CLI Joomla подставляет `live_site` в маршруты (`/set/by/console/application`), поэтому консольным командам адрес сайта нужен из настроек.

## 5. Схема БД

Общее: InnoDB, `utf8mb4_unicode_ci`, даты `DATETIME NULL`, `checked_out`/`checked_out_time` там, где есть редактирование в админке. JSON-столбцы — только для редких, никогда не фильтруемых данных (`params`).

### 5.1 Категории, бренды, бейджи

Штатные `#__categories` (дерево, права, состояние, метаданные, изображение) в трёх секциях через столбец `extension`. **Три раздела меню, типы не смешиваются** (C8):

| Раздел меню / секция (`extension`) | Что это | Связь с товаром | Вложенность | Поля сверх штатных (`params`) |
|---|---|---|---|---|
| Категории / `com_dharmashop` | категория | основная (`products.catid`, адрес товара) + дополнительные | да | `show_in_list`, `list_limit`, `list_ordering`, `new_product_position`, изображение |
| Бренды / `com_dharmashop.brand` | бренд | один на товар | **да** (производитель → линейка) | то же + `brand_website` |
| Бейджи / `com_dharmashop.badge` | бейдж | несколько на товар | нет | `badge_color` (класс Bootstrap), `badge_show_list`, изображение |

**Экран — наш, не `com_categories`** (E17): `CategoriesModel`/`CategoryModel` + `CategoriesController`/`CategoryController` + виды `categories` (список одного типа, параметр `extension`) и `category` (форма). Список: корневая запись (служебная, только у категорий и брендов), перетаскивание порядка (`saveOrderAjax`), состояние, изображение, отображение, **счётчик товаров по индексу** (опубликованных и всего, включая подкатегории), доступ, ID; тулбар: создать, действия, «Перестроить» (`Table::rebuild`), настройки. Форма: основное (изображение, иконка, вводный текст и полное описание в двух редакторах; в боковой колонке тип, родитель, состояние, доступ, заметка), отображение, поля типа, метаданные, права. Изображение, иконка и вводный текст хранятся в `params` категории, полное описание — в штатном `description`; миниатюра в списке: изображение, иначе иконка. Таблица `CategoryTable` наследует штатную `Joomla\CMS\Table\Category`; единственное отличие — корневые записи типов наследуют права компонента (`_getAssetParentId`). Имена ресурсов прав — штатные: `<extension>.category.<id>`.

Правила: тип задаётся при создании (параметр `extension`) и не меняется; родитель — корень или запись того же типа; удалять можно только пустые записи (без вложенных и товаров), потому что штатное удаление дерева сносит потомков; перемещение записи в дереве перестраивает родительские строки индекса у товаров поддерева (`Indexer::rebuildCategorySubtree`).

Все три типа хранят привязку товара **в одной таблице витрины** `product_categories` (раздел 5.4): идентификаторы категорий в Joomla общие на все секции, поэтому листинг бренда или бейджа — тот же индексный проход, что и листинг категории; фильтр «категория + бренд» — проверка по первичному ключу `(catid, product_id)`.

Дополнительно: `#__dharmashop_category_fields (catid, field_id)` — назначения определений полей выбранным категориям (для `all_categories = 0`). С C50 поле имеет `target`: `product` задаёт характеристики товаров этих категорий, `category` — дополнительные значения самих записей категорий. Таблица связей не хранит значения.

### 5.2 Товар — `#__dharmashop_products`

```sql
CREATE TABLE `#__dharmashop_products` (
  `id`            int unsigned NOT NULL AUTO_INCREMENT,
  `asset_id`      int unsigned NOT NULL DEFAULT 0,
  `catid`         int unsigned NOT NULL,                -- основная категория
  `title`         varchar(255) NOT NULL,
  `alias`         varchar(190) NOT NULL,
  `introtext`     text NULL,
  `fulltext`      mediumtext NULL,
  `search_text`   text NULL,                            -- «текст для поиска»: синонимы, разговорные названия (вводится вручную, на странице не показывается)
  `state`         tinyint NOT NULL DEFAULT 0,           -- 1 опубликован, 0 снят, 2 архив, -2 корзина
  `variants_mode` tinyint unsigned NOT NULL DEFAULT 1,  -- 1 один вариант, 2 несколько
  `unit`          varchar(20) NULL,                     -- правила оформления заказа: NULL = наследовать (C14, §5.16)
  `qty_min`       decimal(12,3) NULL,
  `qty_step`      decimal(12,3) NULL,
  `qty_max`       decimal(12,3) NULL,                   -- 0 = без ограничения
  `stock_tracked` tinyint unsigned NULL,                -- NULL = наследовать; итог копируется в варианты
  `backorder`     tinyint unsigned NULL,
  `price_zero_free` tinyint unsigned NULL,              -- NULL = наследовать; 1 — цена 0 значит «бесплатно» (не «по запросу»), C82
  -- производные (пересобираются из вариантов, ProductRebuilder):
  `price_min`     decimal(15,2) NULL,                   -- минимальная эффективная цена основного прайс-листа; NULL = по запросу
  `price_max`     decimal(15,2) NULL,
  `in_stock`      tinyint unsigned NOT NULL DEFAULT 1,
  `variants_count` smallint unsigned NOT NULL DEFAULT 1,
  `cover`         varchar(500) NULL,                    -- главное фото (копия из поля-обложки)
  `cover_alt`     varchar(255) NULL,
  `metakey`       text NULL,
  `metadesc`      text NULL,
  `metadata`      text NULL,
  `params`        text NULL,
  `checked_out`      int unsigned NULL,
  `checked_out_time` datetime NULL,
  `created`       datetime NOT NULL,
  `created_by`    int unsigned NOT NULL DEFAULT 0,
  `modified`      datetime NULL,
  `modified_by`   int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cat_alias` (`catid`, `alias`),
  KEY `idx_state_created` (`state`, `created`),
  KEY `idx_state_price` (`state`, `price_min`, `id`),   -- сортировки страниц без категории (корень, поиск, модуль товаров)
  KEY `idx_state_title` (`state`, `title`, `id`),
  KEY `idx_modified` (`modified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Правила: в списках не читаем `fulltext` и `params`; алиас уникален в основной категории, канонический адрес — по основной категории; никакого `SELECT *` в витрине.

### 5.3 Вариант, цены, остатки

```sql
CREATE TABLE `#__dharmashop_variants` (
  `id`          int unsigned NOT NULL AUTO_INCREMENT,
  `product_id`  int unsigned NOT NULL,
  `sku`         varchar(100) NULL,
  `title`       varchar(255) NULL,                      -- NULL: строится из значений осей
  `state`       tinyint NOT NULL DEFAULT 1,
  `is_default`  tinyint unsigned NOT NULL DEFAULT 0,
  `ordering`    int NOT NULL DEFAULT 0,
  `stock_tracked` tinyint unsigned NOT NULL DEFAULT 0,  -- 0: остаток не ведём, всегда в наличии
  `backorder`   tinyint unsigned NOT NULL DEFAULT 0,    -- 1: продавать при нулевом остатке
  `stock_total` decimal(12,3) NOT NULL DEFAULT 0,       -- производное: доступно (qty − reserved) по всем складам
  `in_stock`    tinyint unsigned NOT NULL DEFAULT 1,    -- производное
  `weight`      decimal(10,3) NULL,
  `weight_unit` varchar(20) NOT NULL DEFAULT 'кг',       -- единица веса для доставки
  `length`      decimal(10,2) NULL,
  `width`       decimal(10,2) NULL,
  `height`      decimal(10,2) NULL,
  `dimension_unit` varchar(20) NOT NULL DEFAULT 'см',    -- общая единица длины, ширины и высоты
  `params`      text NULL,
  `created`     datetime NOT NULL,
  `modified`    datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sku` (`sku`),
  KEY `idx_product` (`product_id`, `state`, `ordering`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

`in_stock = stock_tracked = 0 OR stock_total > 0 OR backorder = 1`.

| Таблица | Столбцы |
|---|---|
| `#__dharmashop_price_lists` | `id`, `title`, `currency` char(3) NULL (NULL → валюта из настроек), `is_default`, `state`, `ordering`, `params`. При установке создаётся основной прайс-лист `id = 1`. В v1 других нет; мультивалютность и оптовые цены — это дополнительные прайс-листы (этап 4) |
| `#__dharmashop_variant_prices` | `variant_id`, `price_list_id`, `price` decimal(15,2) NULL (NULL = «по запросу»), `discount` decimal(15,2) NULL (сумма или проценты), `discount_percent` tinyint (1 — проценты), `discount_start` NULL, `discount_end` NULL, `price_final` decimal(15,2) NULL (эффективная; пересчитывается при сохранении и задачей планировщика). PK `(variant_id, price_list_id)`, `KEY (sale_start)`, `KEY (sale_end)` |
| `#__dharmashop_stock_locations` | `id`, `title`, `is_default`, `state`, `ordering`. При установке — один склад `id = 1`. Склады в интерфейсе — этап 4 |
| `#__dharmashop_stock_levels` | `variant_id`, `location_id`, `qty` decimal(12,3), `reserved` decimal(12,3). PK `(variant_id, location_id)` |
| `#__dharmashop_stock_movements` | журнал: `id` bigint, `variant_id`, `location_id`, `delta`, `reason` (order/manual/import/return/…), `order_id` NULL, `user_id` NULL, `note`, `created`. `KEY (variant_id, created)`, `KEY (order_id)` |

Остаток меняется только через `StockService` (пишет движение, пересчитывает `stock_total`/`in_stock` варианта). Событие `onDharmaShopStockChange` запланировано и пока не вызывается. Затем (если вызывающий код не пересчитывает товар сам, как сохранение карточки) наличие переносится на `products.in_stock`, копию `product_categories.in_stock`, итоги группы (`ProductRebuilder::rebuildProducts`, `Indexer::refreshProducts`) и сбрасывается кэш фильтра. Заказ и смена статуса с возвратом остатка повторно сбрасывают кэш уже после коммита.

### 5.4 Витринный индекс — `#__dharmashop_product_categories`

Одна строка на пару «товар — категория / бренд / бейдж», **включая все родительские записи в той же секции** (родитель ищется по `lft/rgt` внутри того же `extension`). Список любой категории (с подкатегориями или без) — индексный проход по одной таблице, без `IN` и дублей.

```sql
CREATE TABLE `#__dharmashop_product_categories` (
  `catid`       int unsigned NOT NULL,
  `product_id`  int unsigned NOT NULL,
  `direct`      tinyint unsigned NOT NULL,        -- 1: товар назначен в эту категорию, 0: унаследовано
  `is_main`     tinyint unsigned NOT NULL DEFAULT 0,
  `state`       tinyint NOT NULL,                 -- копия products.state
  `sort_order`  int NOT NULL DEFAULT 0,           -- ручной порядок, свой в каждой категории
  `price_sort`  decimal(15,2) NULL,               -- = products.price_min
  `in_stock`    tinyint unsigned NOT NULL,
  `created`     datetime NOT NULL,
  `title_sort`  varchar(60) NOT NULL,
  PRIMARY KEY (`catid`, `product_id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_l_order` (`catid`, `state`, `sort_order`, `product_id`),
  KEY `idx_l_price` (`catid`, `state`, `price_sort`, `product_id`),
  KEY `idx_l_new`   (`catid`, `state`, `created`, `product_id`),
  KEY `idx_l_title` (`catid`, `state`, `title_sort`, `product_id`),
  KEY `idx_l_stock` (`catid`, `state`, `in_stock`, `sort_order`, `product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Это осознанная денормализация ради скорости; источник правды — `products`, индекс всегда можно перестроить (`reindex`). `Indexer` обновляет строки товара при сохранении, смене состояния и цены; поддерево — при перемещении категории.

Запись: `Indexer::setCategories(товар, основная, дополнительные, бренд, бейджи, newProductPosition, categoryPositions)` задаёт прямые привязки (`direct = 1`, ручной `sort_order` у сохраняемых не теряется), затем `rebuildRange()` пересобирает родительские строки и копии столбцов. Новые связи товара с категориями получают `sort_order` перед текущим минимумом или после текущего максимума по эффективному параметру `new_product_position`: настройка самой категории → ближайшего родителя → общие настройки компонента (`first`/`last`, глобально по умолчанию `last`). Решение применяется отдельно к каждой категории в иерархии; существующий порядок не меняется. Ручной порядок действующих унаследованных строк сохраняется во временной таблице на время перестройки; недействующие родительские связи исчезают. То же применяется к диапазону id при полной пересборке `rebuildAll()` и будущей команде `reindex`.

В карточке товара вкладка «Порядок» показывает строки индекса для всех категорий товара (прямых и унаследованных). `Indexer::setCategoryOrder()` принимает для каждой существующей связи режим «Первый», «Последний» или заданное вручную целое значение. Для первых двух режимов берётся текущее минимальное/максимальное значение категории и добавляется/вычитается единица; категории вне индекса по переданному запросом `catid` не изменяются.

Страница списка: 1) выбрать `product_id` страницы по индексу; 2) загрузить карточки `WHERE id IN (...)` по первичному ключу. Постраничность штатная для небольших смещений, на больших — курсор по `(ключ сортировки, product_id)`; порог — в настройках. Общее число — `COUNT` с верхним пределом, для категорий без фильтра — кэш.

### 5.5 Поля, группы и значения

C62 (2026-10-08): text/editor имеют заблокированное filterable=0: нативный showon формы, эффективное чтение в FieldModel и принудительная запись в FieldTable. Движок фильтра ещё не реализован; при его добавлении исключать эти типы независимо от старых флагов в БД. FIELDS.md §20.

C61 (2026-10-08): editor не загружается и не выводится в card. Форма показывает отключённые Нет/Нет и формат, server normalizer закрепляет load_card=0, Table — in_card=0; EntityFields исключает editor до чтения значений, включая старые определения. Контекст page не изменён, миграция данных не требуется. FIELDS.md §19.

C60: провайдер basic выводит действующие ограничения значений нативными description в форме товара/категории и возвращает ошибки с конкретным нарушенным правилом/пределом. Общая генерация одинакова для обоих назначений; схема и контракт ValueTypeInterface не изменены. Подробнее FIELDS.md §18.

**Актуально C59:** нативные элементы полей подключены к редакторам товара/категории во вкладках групп после «Основное». `Fields\FieldValues` управляет применимостью, XML и записью; провайдер `ValueTypeInterface` возвращает input/default и преобразует значение в строки/текст. Значения предварительно проверяются до Table::store и записываются транзакцией связей после него. Маркеры `custom_fields_present` позволяют очищать набор чекбоксов; отсутствующие/неприменимые поля сохраняются. Default применяется только к новым формам. Подробности и ограничение атомарности Joomla — FIELDS.md §17.

Для категорий реализованы `#__dharmashop_category_field_values` (`id,category_id,field_id,option_id,num,num_end,str`) и `#__dharmashop_category_field_texts` (`id,category_id,field_id,value`). Индексы простых значений: `(category_id,field_id)`, `(field_id,option_id,category_id)`, `(field_id,num,category_id)`, `(field_id,num_end,category_id)`, `(field_id,str,category_id)`; текст — `(category_id,field_id)`. В product `field_values` добавлен nullable `num_end DECIMAL(18,6)` и `idx_num_end(field_id,num_end,product_id)`; num/num_end — две границы range. Миграция `upgradeFieldValueStorage()` аддитивная, категория никогда не записывается в product_id. Тип `files` добавляет `category_field_media` (`category_id,field_id,path,alt,title,filename,bytes,mime,ordering`) и nullable метаданные файла в product `field_media`; индекс категории `(category_id,field_id,ordering)`. Аддитивное обновление `FileStorage::upgrade()`, подробности — `FILES_FIELD.md`. `category_fields` остаётся применимостью определений.

`Site\Support\EntityFields` читает значения обоих назначений пакетно для card/page; совместимый `ProductFields` делегирует ему. Категории подключены только в Taxonomy::category, бренды/бейджи не получают их значения. Макеты используют прежние dl/dt/dd, load/show/format; диапазон готов к выводу. Приведённые ниже записи C53/C57 описывают состояние на соответствующем шаге и дополнены C59.

C58: default чекбокса разделён по режимам: булев селект для single, название опции в input для options. Компонент разрешает название в стабильный `checkbox_default_option_id` в конфигурации params, проверяет принадлежность/однозначность и сохраняет новый ID в транзакции опций. Значения товаров не переписываются, C59 применяет default к новым формам. Схема C58 без изменений; FIELDS.md §16–17.

C57: независимые настройки загрузки/вывода и формата для списка/страницы; общие настройки конфигурации нормализует `Fields\DisplaySettings`. `filterable` сохраняется, вариативность зарезервирована в params и разрешена только list; движки фильтра/вариантов пока не реализованы. `Site\Support\ProductFields` читает значения опубликованных доступных полей пакетно для текущей страницы товаров, с проверкой применимости; `item.fields[alias]` передаётся макетам `components.dharmashop.fields.default/value` (dl/dt/dd). Вывод включён в карточки и страницу товара; точки вывода категорий готовы, их отдельного хранилища ещё нет. Ввод значений и range остаются следующими задачами. Схема БД не изменена. Подробности и ограничения — FIELDS.md §15.

Исследование компонента/стандартного плагина RadicalMart и проект нашей подсистемы — `FIELDS.md` (2026-10-07, направление принято C49). Админка групп, реестр и экраны определений полей реализованы (C53); ввод/хранение конкретных значений и вывод характеристик — следующие шаги. У поля `in_card` отдельно управляет показом в тизере списка, `in_page` — полной страницей; настройки сохраняются, фактический вывод ещё не подключён. По C54 назначение, тип и группа в отдельном fieldset сайдбара; назначение и тип неизменны после первого сохранения в форме и Table. Плагин `dharmashopfield/basic` поставляется пакетом с отдельным обработчиком/формой каждого из шести типов. Подробнее контракт и ограничения — FIELDS.md §13.

Группы: `FieldgroupsModel`, `FieldgroupModel`, `FieldgroupTable` и нативные контроллеры/виды/макеты Joomla. Права наследуются от компонента (у таблицы нет `asset_id`); Searchtools, пагинация, публикация, архив, корзина, check-in и нативный draggable. Алиас уникален; новые записи получают следующий порядок. Удаление допускается из корзины, таблица блокирует его при любых связанных полях. Скрытие группы не удаляет значения. C51 подключает размещение опубликованных групп в нативных вкладках товара/категории; генерация дополнительных полей, сохранение их значений и витрина ещё не подключены. В C49 схема не менялась; C50 добавил `target`, C51 — `tab_mode` аддитивными обновлениями.

| Таблица | Столбцы |
|---|---|
| `#__dharmashop_fieldgroups` | `id`, `target` (`product`/`category`, по умолчанию `product`), `tab_mode` (`fields`/`group`, по умолчанию `fields`), `title`, `alias`, `ordering`, `state`, `checked_out*` |
| `#__dharmashop_fields` | `id`, `target` (`product`/`category`, по умолчанию `product`), `group_id`, `alias` (`UNIQUE`), `title`, `type` (id типа в реестре), `store` (`option`/`number`/`string`/`text`/`range`; media зарезервирован), `level` (для товаров: 1 товар, 2 вариант), `all_categories`, `filterable`, `sortable`, `in_card` (плитка), `in_page` (страница), `is_cover` (обложка товара), `required`, `unit`, `ordering`, `state`, `access`, `params` (настройки типа), `checked_out*` |
| `#__dharmashop_field_options` | `id`, `field_id`, `alias`, `title`, `color`, `image` (необязательные), `ordering`; `UNIQUE (field_id, alias)` |
| `#__dharmashop_product_axes` | `product_id`, `field_id`, `ordering` — по каким полям уровня «вариант» построены варианты товара; PK `(product_id, field_id)` |

Тип `color` (2026-10-09, запрос пользователя): тот же store=option и существующие field_options.color/image, без миграции схемы. Нативный повторяемый выбор источника образца (колорпикер/Media), проверенный HEX или локальное изображение, стабильные ID и только одиночный ввод. Независимые select/radio для админки, информационного показа на сайте и фильтра (radio по умолчанию); variation разрешён для color/list. Пакетный EntityFields подготавливает данные для fields/color.php; изображения используют собственные field.color.admin/filter/card/page, сплошные образцы — SVG с проверенным fill. Подробности — FIELDS.md §22.

Изображения вариантов (C56): общая таблица опций списка/чекбокса использует нативный `subform` с `groupByFieldset=true`. Первая ячейка объединяет скрытый стабильный ID и Media-поле, следующие — заголовок и алиас. `FieldModel` загружает/сохраняет `image` в существующем столбце (до 500 символов), включая служебный хвост Joomla Media. Пустая строка убирает привязку; файлы медиаменеджера не удаляются. Схема БД не изменяется, отображение картинки значения на витрине пока не подключено. Компактный `CompactmediaField` наследует нативный Media и добавляет класс оболочки; превью 48×48 и строка адреса/кнопок оформлены локальными правилами в существующем `uploader.css`. Штатный Media JS и работа subform сохранены.

Назначение C50/C52: группы товаров и категорий раздельные, типы полей/валидация общие. По уточнению C52 `target` неизменен после первого сохранения, даже у пустой группы. `FieldgroupModel::getForm` для существующей записи показывает сохранённое назначение и задаёт нативные `disabled=true`, `required=false`, `filter=unset`; обычное сохранение сохраняет значение из загруженной таблицы. `FieldgroupTable::check` дополнительно отвергает любую смену `target` у существующего ID, независимо от количества полей. Выбор назначения и фильтр нативные Joomla; draggable включается при фильтре одного назначения, `FieldgroupModel::getReorderConditions` ограничивает пересчёт порядков этим назначением. Пакетный `script.php::upgradeFieldTargets` перед обновлением добавляет отсутствующий `target` в существующие таблицы; первая установка берёт обновлённый SQL. Данные не удаляются, прежние записи получают `product`. При создании формы поля необходимо проверять соответствие назначения поля и группы, поле без группы имеет собственное назначение. Форма поля и заполнение категорий пока не подключены.

Вкладки C51: `Support\FieldGroupTabs` загружает опубликованные группы текущего назначения одним запросом, сортирует по `ordering,id`. Административный макет `admin/layouts/field-tabs.php` собирает общую вкладку «Поля» (блоки по группам) и отдельные вкладки с названиями групп через `uitab`. Общая вкладка отсутствует, если для неё нет групп/полей без группы. Поля без группы используют `dharmashop_fields_group_0`; группы — `dharmashop_fields_group_<id>`. Это внутренние имена fieldset `Form`, которые будет заполнять движок полей с проверкой применимости и прав. Сейчас пустые группы показывают сообщение. Бренды/бейджи не получают группы категорий. `tab_mode` меняется без переноса значений; название вкладки обновляется при переименовании группы, ID вкладки основан на ID группы. Новый административный layout не относится к библиотеке витрины. Обновление пакета `upgradeFieldGroupTabs()` идемпотентно добавляет колонку к действующей базе.

Порядок вкладок формы: сразу после «Основное» выводится весь блок дополнительных полей (общая «Поля», затем отдельные вкладки групп); далее у товара «Цена и остаток», у категории «Отображение». При отсутствии общей вкладки вторая — первая отдельная группа.

Форма **определения поля** (C55) отдельно имеет ровно две вкладки: «Основное» и «Отображение». В «Основное» находятся общие параметры и настройки выбранного типа с опциями; нативный `showon` реагирует на тип в сайдбаре. Сайдбар: рамки «Назначение и тип» (назначение/тип/группа), «Применение» (все/выбранные категории), «Публикация». Имена полей и формат сохранения не изменены.

Значения товаров (реализованная схема):

```sql
CREATE TABLE `#__dharmashop_field_values` (
  `id`         bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int unsigned NOT NULL,        -- всегда заполнен (и у полей варианта: товар-владелец)
  `variant_id` int unsigned NULL,            -- NULL: поле товара; иначе поле варианта
  `field_id`   int unsigned NOT NULL,
  `option_id`  int unsigned NULL,            -- store=option
  `num`        decimal(18,6) NULL,           -- number, bool либо нижняя граница range
  `num_end`    decimal(18,6) NULL,           -- верхняя граница range (C59)
  `str`        varchar(190) NULL,            -- store=string
  PRIMARY KEY (`id`),
  KEY `idx_product`  (`product_id`, `field_id`),
  KEY `idx_variant`  (`variant_id`, `field_id`),
  KEY `idx_option`   (`field_id`, `option_id`, `product_id`),
  KEY `idx_num`      (`field_id`, `num`, `product_id`),
  KEY `idx_num_end`  (`field_id`, `num_end`, `product_id`),
  KEY `idx_str`      (`field_id`, `str`, `product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- `#__dharmashop_field_texts` (`id`, `product_id`, `variant_id` NULL, `field_id`, `value` mediumtext; `KEY (product_id, field_id)`) — длинный текст и HTML, в фильтре не участвует.
- `#__dharmashop_field_media` (`id`, `product_id`, `variant_id` NULL, `field_id`, `path` varchar(500), `alt` varchar(255), `title` varchar(255), `ordering`; `KEY (product_id, field_id, ordering)`) — **галерея, файлы, изображения**. Дополнительно nullable `filename`, `bytes`, `mime` для типа files. Основная галерея `field_id=0` сохраняет NULL в этих столбцах; пользовательские файлы записываются под собственным `field_id`. Общий загрузчик изолирует каждый экземпляр; отдельный макет `fields/files.php`. См. `FILES_FIELD.md`.
- Мультивыбор — несколько строк `option_id` на пару «товар/вариант — поле».
- Фильтр по полю — `EXISTS (SELECT 1 FROM field_values WHERE product_id = pc.product_id AND field_id = ? AND option_id IN (...))`; число — по диапазону `num`. Поле варианта находит товар, если подходит **любой** его вариант; строгое совпадение «этот цвет и этот размер в одном варианте» возможно (есть `variant_id`), но в v1 не включено.
- Счётчики значений фильтра («Красный (12)») дорогие: считаются по требованию, кэшируются, включаются настройкой.
- Типы полей расширяются плагинами группы `dharmashopfield`: тип сообщает `store`, XML редактирования, логику фильтра и вывода.

Значения категорий (C50/C59): реализованы отдельные индексируемые простые и текстовые хранилища с `category_id`, перечисленные выше; медиа реализованы типом `files` и таблицей `category_field_media` (см. `FILES_FIELD.md`). Существующие `field_values`/`field_texts`/`field_media` остаются товарными, служебная галерея `field_id=0` сохраняется. Дополнительные поля категории не включаются автоматически в фильтр товаров. **Фильтр (C84):** `field_values.idx_product` покрывает `(product_id, field_id, option_id, num)`; предрасчёт базовых счётчиков и границ — `#__dharmashop_filter_facets (catid, field_id, option_id, products)` и `#__dharmashop_filter_ranges (catid, field_id, num_min, num_max)` (`catid = 0` — весь каталог), сопровождаются `ShopServices::facets()` (`FacetIndexer`); условия и определения — `ShopServices::filter()`. Подробности — `FILTER.md`. Подробности — `FIELDS.md` §17.

### 5.6 Поиск

`#__dharmashop_search`: `product_id` PK, `title_norm` varchar(255), `body` mediumtext (описание без HTML + `products.search_text` + значения строковых полей); `FULLTEXT (title_norm, body)`. Поиск по артикулу — префиксный по `variants.sku` (`uq_sku`). Интеграция с Умным поиском Joomla — плагином `finder`. **Реализовано (C78, предложено):** индекс наполняют `SearchIndexer` (при сохранении товара и в «Перестроить индекс»), запрос — `Site\Search\ProductSearch` (FULLTEXT по началу слов, короткие слова по названию, артикул по префиксу), поиск работает параметром `q` на страницах каталога, категорий, брендов и бейджей, подсказки — `SearchController::suggest`, строка — модуль `mod_dharmashop_search`; подробности — `docs/SEARCH.md`.

### 5.7 Корзины

```
#__dharmashop_carts       id, token char(32) UNIQUE (в cookie), user_id NULL, customer_id NULL,
                          price_list_id, created, modified, expires; KEY (user_id), KEY (expires)
#__dharmashop_cart_items  id, cart_id, variant_id, qty decimal(12,3), line_hash char(32),
                          params text (JSON: срок аренды и т. п.), UNIQUE (cart_id, line_hash), KEY (variant_id)
```

Цена в корзине не хранится: пересчитывается при каждом чтении (`PriceResolver`), в заказ уходит снимок. Итоги собираются событием; плагины добавляют строки «скидка», «налог», «сбор», «доставка».

### 5.7а Избранное (C88)

```
#__dharmashop_favorites       id, token char(64) NULL UNIQUE, user_id NULL UNIQUE, created, modified
#__dharmashop_favorite_items  id, favorites_id, product_id, created,
                              UNIQUE (favorites_id, product_id), KEY (product_id, favorites_id)
```

Один список на Joomla-аккаунт или гостевой токен. Cookie гостя случайная (256 бит), HttpOnly, SameSite=Lax, Secure на HTTPS; токен не показывается в админке и не используется как ссылка. После входа гостевой список прикрепляется к `user_id` или объединяется с ним с дедупликацией. Пользователи сопоставляются только по Joomla `user_id`, не по контактам. Модуль выводит число и ведёт на `view=favorites`; изменение выполняется POST с CSRF-токеном Joomla. Админский просмотр требует `dharmashop.favorites.view`; гостевые записи остаются анонимными. В v1 сохраняются самостоятельные товары; сравнение использует отдельные данные и обработчики.

Страница `view=favorites` рендерит общие карточки товара. Общие параметры `favorites_card_layout_source`, `favorites_card_layout` и `favorites_columns` задают источник, вид карточек и число колонок по умолчанию; параметры пункта меню с теми же ключами могут переопределить эти значения. В пункте меню также задаются `favorites_show_heading`, `favorites_page_heading`, `favorites_introtext` и `favorites_fulltext`. Вступительный текст выводится перед списком, полный — после него. Переопределение карточек использует обычный путь Joomla `html/layouts/components/dharmashop/product-card/<вариант>.php`; макет самой страницы стандартно переопределяется в `templates/<шаблон>/html/com_dharmashop/favorites/default.php`. Иконка сердца остаётся самостоятельным JLayout без отдельного переключателя.


### 5.8 Покупатели

`#__dharmashop_customers`: `id`, `user_id` NULL (`UNIQUE`), `name`, `email` (`KEY`), `phone`, `phone_norm` (`KEY`), `contact_key` char(64) NULL (`UNIQUE uq_guest_contact`), `note`, `params`, `created`, `modified`. C78 заменяет определение гостя C73 по прежней корзине: полная совпадающая пара нормализованных email/телефон даёт SHA-256 contact_key и одну гостевую карточку, атомарный INSERT/ON DUPLICATE исключает гонку разных корзин. Неполная пара — новая запись с NULL; аккаунты исключительно по user_id, с гостями не объединяются. Непустое имя карточки не перезаписывается; введённое имя остаётся в снимке заказа. CustomerTable пересчитывает ключ при ручном изменении контактов и отклоняет дубль. CustomerStorage обновляет схему/ключи аддитивно, исторические дубли не удаляет. CustomerService работает внутри транзакции заказа; контактная CRM-связь не меняет авторизацию заказов. Подробнее CUSTOMER_IDENTITY.md.

### 5.9 Заказы

Уточнение email-оформления 2026-10-08: NotificationService загружает позиции с необязательными available_product_id/cover текущего опубликованного товара одним LEFT JOIN по products.id. Финансовые данные, название и артикул остаются из order_items. Email ITEMS показывает фото и кликабельный заголовок; HtmlTemplate встраивает свои локальные изображения через MIME inline/CID с лимитом размера, внешние файлы не скачивает. Снимки заказов не меняются. NOTIFICATIONS.md / SHORTCODES.md.

C76 (2026-10-08): `NotificationService` выдаёт readonly `OrderNotification` канальным плагинам `dharmashopnotify` после commit. Новый заказ вызывает событие один раз, повтор checkout не вызывает. `OrderStatusService` меняет активные нейтральные статусы с блокировкой заказа, проверкой ожидаемого прежнего статуса и таблицы переходов, записывает историю и запускает уведомления после commit. Движения остатков пока запрещены. Нативное действие в карточке заказа, POST/CSRF/orders.manage. Текст статуса — customer_message. `#__dharmashop_notifications`: id/order_id/history_id/channel/audience/recipient/recipient_hash/claim_key/state/attempts/error_code/created/sent; UNIQUE(history_id,channel,audience,recipient_hash), KEY(order_id,history_id). State 0 — начато без зафиксированного результата, 1 — принято транспортом, 2 — ошибка. Ручной повтор только state=2, успешные и неопределённые отправки не повторяются. Email-плагин отдельно, общие настройки в config.xml; SMTP Joomla, отправка default=0. NOTIFICATIONS.md.

C76. Telegram-канал реализован самостоятельным плагином `plg_dharmashopnotify_telegram` в группе `dharmashopnotify`. Настройки канала расположены в общей вкладке «Уведомления» компонента: токен, chat_id/@username, new/status, состав/контакты/ссылка. Отправляет plain text через Bot API и отдельно claim/finish по каждому чату как `channel=telegram,audience=admin`; клиентские уведомления не предусмотрены без сохранённого Telegram chat_id. Секрет и описание ответа Telegram в журнал не попадают. Канал выключен по умолчанию и добавляется в общий пакет.

C75 (2026-10-08): `Site\Model\OrderModel` читает заказ по PK с условием владельца в SQL до получения контактов/позиций. Аккаунт — `orders.user_id`; гость — серверное разрешение `OrderAccess` после успешного POST и только `user_id IS NULL`. Сессия хранит последние 50 ID; старый receipt C73 принимается в течение его 30 минут. ID/номер/email/secret из URL не дают доступа; чужой и отсутствующий заказ возвращают одинаковый 404. Кеш отключён, HTTP private/no-store, robots noindex/nofollow. Secret, внутренние заметки и история сотрудников не выбираются. `OrderDisplay` форматирует сохранённые цены/валюту без каталога. Вид `order`, тип меню «Заказ», части items/contacts/summary. Подтверждение checkoutcomplete теперь загружает доступный снимок и показывает дату/статус/сумму с действиями; это заменяет ограничение C74 «без SQL».

C74: отдельный Site\View\Checkoutcomplete\HtmlView, маршрут checkoutcomplete и тип меню «Заказ оформлен». POST перенаправляет туда; checkout?completed=1 — совместимый переход. Только номер из собственной сессии C73 (user_id, срок 30 минут), без SQL/загрузки корзины; без подтверждения нейтральное сообщение. robots=noindex/nofollow, кеш отключён. Пункт меню даёт независимые адрес и назначение модулей.

C73 (2026-10-08): список/профиль покупателей, список/снимок заказов и редактор статусов. CheckoutController::submit проверяет POST+CSRF, гостевой доступ, ключ сессии, контакты/согласия C70; OrderService::create повторно читает корзину/цены/ошибки, записывает покупателя и снимки заказа/позиций/итогов/истории, очищает корзину одной транзакцией без Table::store. Cart row и строки корзины FOR UPDATE, UNIQUE secret и ключ с user_id/cart_id защищают повтор. Номер C73 — ID с минимум шестью цифрами; настраиваемый формат далее.

C70: checkout_fields и checkout_consents — определения в params, нативные subform/CheckoutdefinitionsField/Rule. Пустые технические имена генерируются до проверки. Типы text/tel/email/textarea; mask по умолчанию +7 (###) ###-##-##, show_label (1) управляет подписью/плейсхолдером. CheckoutFields — общие определения и серверная проверка. checkout_guest=1 разрешает гостя; 0 требует входа на GET/POST. Согласия сняты, снимок точного текста/URL и времени в orders.contacts. Схема БД не менялась.

C71/C73: checkout — отдельный вид и тип меню; части default/form/contacts/agreements/summary/success, Bootstrap без CSS. checkout.js форматирует телефон и защищает кнопку от повтора. Ошибки возвращают поля через сессию; success показывает номер из неё (30 минут), без публичного просмотра по ID/секрету. Кеш/индексация отключены, каталог закрыт. Доставка/оплата и смена статусов с движением остатков — следующие шаги. CHECKOUT.md §7, ORDERS.md, LAYOUTS.md.

| Таблица | Столбцы |
|---|---|
| `#__dharmashop_orders` | `id`, `number` (`UNIQUE`), `secret` char(32), `kind` (1 заказ, 2 заявка), `customer_id`, `user_id`, `status_id` (`KEY`), `currency`, `subtotal`, `adjustments_total`, `total` decimal(15,2), `contact_name`, `contact_email`, `contact_phone` (`KEY`), `contacts` (JSON: прочие поля оформления), `note`, `shipping_method_id`, `shipping_title`, `shipping_data`, `payment_method_id`, `payment_title`, `payment_data`, `cart_id`, `created` (`KEY`), `created_by`, `modified`, `modified_by`, `checked_out*` |
| `#__dharmashop_order_items` | `id`, `order_id` (`KEY`), `variant_id` NULL, `product_id` NULL, `sku`, `title`, `unit`, `qty`, `price`, `total`, `params`, `ordering` — снимок на момент заказа |
| `#__dharmashop_order_adjustments` | `id`, `order_id`, `type` (shipping/discount/tax/fee), `code`, `title`, `amount` (может быть < 0), `params`, `ordering` |
| `#__dharmashop_order_history` | `id`, `order_id`, `status_id`, `user_id` NULL, `comment`, `notified`, `created`; `KEY (order_id, created)` |
| `#__dharmashop_payments` | `id`, `order_id`, `method_id`, `amount`, `currency`, `state` (pending/paid/failed/refunded), `external_id` (`UNIQUE` вместе с `method_id`: защита от повторных callback), `data`, `created`, `modified` |
| `#__dharmashop_statuses` | `id`, `title`, `alias` (`UNIQUE`), `color` (класс Bootstrap), `is_default`, `is_final`, `stock_effect` (0 нет / 1 резерв / 2 списание / 3 снять резерв), `notify_customer`, `notify_admin`, `customer_message`, `ordering`, `state` |
| `#__dharmashop_status_transitions` | `from_status_id`, `to_status_id`; PK обоих. Если у статуса переходов нет — разрешены любые |

План следующих шагов: настраиваемый формат номера (C73 пока ID с нулями), эффекты остатков и резерв в `OrderStatusService` (C76 уже подключил нейтральные переходы, историю, письма и событие). Заявка — заказ с `kind = 2`: позиция может быть без цены, оплаты нет.

### 5.10 Способы доставки и оплаты

```
#__dharmashop_shipping_methods  id, title, alias UNIQUE, plugin (element плагина группы dharmashopshipping),
                                description, params (JSON настроек плагина), is_default, state, access, ordering, checked_out*
#__dharmashop_payment_methods   то же; plugin — группы dharmashoppayment; + paid_status_id (статус заказа после оплаты, C83)
#__dharmashop_shipping_methods  + payment_ids varchar(255): «1,3» — разрешённая оплата для этого способа доставки (пусто — любая)
#__dharmashop_orders            + payment_status (none|pending|paid|failed|cancelled), paid (время оплаты)
```

Один плагин может обслуживать несколько записей.

Реализация доставки (C80): `Administrator\Shipping\*` (контракт `TypeInterface`, реестр, `ShippingService`), раздел админки `shippings`, типы — плагины `plg_dharmashopshipping_standard` и `plg_dharmashopshipping_pickup`; снимок выбора — `orders.shipping_data` (JSON: `type`, `title`, `price`, `free`, `term`, `rows`, `data`), цена — строка `order_adjustments` типа `shipping`. Правила для плагинов — `docs/SHIPPING.md`; оплата повторяет схему.

### 5.10а Склады (C81)

```
#__dharmashop_warehouses  id, title, alias UNIQUE, image, address, phone, email, hours, info, ready_term, lat, lon (decimal(9,6) NULL),
                          state, access, ordering, checked_out*; KEY (state, ordering)
```

Справочник складов для способов доставки и других частей магазина: `ShopServices::warehouses()`, `docs/WAREHOUSES.md`.

### 5.11 Служебное

`#__dharmashop_external_ids`: `entity`, `source`, `external_id`, `id`; `UNIQUE (entity, source, external_id)` — соответствие внешних идентификаторов (перенос, 1С, YML).

### 5.12 Чего в схеме нет

Языковых версий (C2), доступа по группам к товару и дат публикации (E6), мультивалютных цен и складов в интерфейсе (этап 4, схема готова), истории изменений товара (штатная `com_contenthistory` — позже), просмотров (аналитика — отдельный компонент).

### 5.13 Сохранение товара (реализовано на этапе 1)

`ProductModel::save()` (форма и CLI), порядок важен:

1. **Проверки, которые могут не пройти, — до записи:** уникальность артикула среди вариантов других товаров (`VariantRepository::assertSkuAvailable`). Алиас уникален в основной категории (проверка в `ProductTable::store`).
2. **Карточка:** `AdminModel::save()` (таблица `products`, права/asset, события Joomla). **Её нельзя оборачивать в транзакцию:** `Table::store()` для ресурса прав берёт `LOCK TABLES`, а это неявно фиксирует транзакцию (проверено на практике: откат ничего не убирал).
3. **Зависимые данные — в транзакции:** вариант по умолчанию и его цена (`price_final` по `PriceResolver` с учётом скидки), остаток основного склада и запись движения (`StockService`), производные поля товара (`ProductRebuilder`), привязки и индекс витрины (`Indexer`). Если шаг 3 упал, новая карточка удаляется (компенсация), ошибка пишется в журнал `administrator/logs/com_dharmashop.log.php` и в сообщение формы.

Удаление (`delete`) после штатного удаления карточки чистит варианты, цены, остатки, индекс, поиск, оси; публикация/состояние обновляют копии в индексе (`refreshProducts`).

### 5.14 Команды обслуживания (реализовано на этапе 1)

Производные данные (индекс витрины, цена «от/до» и наличие товара, эффективная цена с акциями) можно пересчитать. Меню «Команды» на списке категорий (аналог RadicalMart), права `core.manage`:

| Команда | Что делает | Служба |
|---|---|---|
| Пересобрать витринный индекс товаров | родительские строки и копии столбцов сортировки для всех товаров; убирает строки несуществующих товаров | `Indexer::rebuildRange` |
| Пересчитать цену и наличие товаров | `price_min`, `price_max`, `in_stock`, `variants_count` из вариантов, затем копии в индексе | `ProductRebuilder::rebuildRange` |
| Пересчитать скидки по датам | у вариантов, у которых скидка началась или закончилась, пересчитывает `price_final`, цену товара и индекс (один набор запросов) | `SaleScheduler::run` |
| Преобразовать загруженные изображения в WebP | ссылки на обложки, SEO-картинки, галереи, изображения категорий и корневых страниц переводятся на проверенные WebP-копии; оригиналы не перезаписываются | `ImageWebpMigration`, отдельный курсор по товарам/категориям/медиа/настройкам |

Дерево категорий пересчитывает отдельная кнопка «Перестроить». Все команды — `Maintenance\MaintenanceService`: проход по товарам порциями по ключу (`id > курсор`), бюджет времени на один запуск (8 с); если каталог не закончился, курсор хранится в сессии пользователя, и повторный запуск продолжает. Эти же службы будут использовать консольная команда `dharmashop:reindex` (полный прогон больших каталогов без ограничений веб-запроса) и задача планировщика Joomla (`SaleScheduler`).
Команды находятся в списке товаров, меню «Команды» (задачи `products.cmdIndex`, `cmdProducts`, `cmdSales`, `cmdWebp`; токен и право `core.manage`); WebP-миграция хранит фазу и ID в сессии и продолжается повторным запуском. В списке категорий осталась только кнопка «Перестроить» (дерево). Действие «Пересохранить» в меню «Действия» (`products.resave`, `ProductModel::resave`) пересчитывает выбранные товары без открытия формы: правила оформления заказа, цена и наличие из вариантов, привязки и копии индекса.


### 5.15 Загрузчик изображений (реализовано на этапе 1, C13)

**Производные C79:** загрузчик и миграция WebP сохраняют прежнее назначение. Общий `ImageUrl::resolve` вызывает плагин размеров; настройки контекстов в отдельном дочернем fieldset вкладки «Изображения». Формат auto следует `image_convert_webp`, явный формат строки имеет приоритет, email ограничен JPEG/PNG. Размер 0 — авто, обе стороны 0 — оригинал. Общий кеш `images/dharmashop/cache/resized/v2/<aa>/<bb>/<размер>-<режим>-<качество>-<хеши>.<формат>` создаётся лениво; контекст не входит в ключ/путь. Ключ учитывает исходник (реальный путь, mtime/длина) и канонические параметры: размеры, режим, реальный формат, качество кодировщика (PNG — постоянное сжатие без потерь). Совпавшие параметры используют одну копию/URL/lock между контекстами. Нет изменения оригиналов, SQL каталога по картинкам, загрузки внешних файлов или автоматического удаления кеша, включая прежний v1. Подробности/контексты/ограничения — IMAGE_RESIZE.md.

Обслуживание производных: нативный вид `imagecommands`, пункты меню «Команды» списка товаров. `ImageMaintenance`/`ImageInventory`/`ImageCache`: порционная подготовка через событие, keyset ID, общий кеш, предпросмотр и подтверждение очистки v1/v2. POST/CSRF/core.manage, серверный курсор, защита ссылок магазина на кеш как источник, symlink и блокировок; исходники сохраняются. Подробности IMAGE_RESIZE.md.

Поле формы `imageupload` (`Field\ImageuploadField`, атрибут `folder` — подпапка): область для перетаскивания файла или выбора по клику, предпросмотр, «Убрать», прогресс. Скрипт — `media/com_dharmashop/js/uploader.js` (стили `css/uploader.css`), активы описаны в `media/com_dharmashop/joomla.asset.json`, подключаются через Web Asset Manager.

Загрузка: `POST index.php?option=com_dharmashop&task=media.upload&format=json` (`MediaController::upload`) с токеном формы; права — `core.create` или `core.edit` компонента. `Image\ImageUploader` проверяет: код загрузки и размер (меньший из настроек «Медиа» Joomla и PHP), расширение из списка настроек «Медиа» (только известные картинки), **тип содержимого** (`finfo`, у растровых ещё `getimagesize`), у SVG — запрет скриптов, `on*=`, `javascript:`, `foreignObject`, `ENTITY`. Имя файла — латиница (`OutputFilter::stringURLSafe`), при совпадении `-2`, `-3`…; подпапка очищается от всего, кроме `a–z 0–9 _ -` (обход каталогов невозможен). Файл сохраняется в `images/dharmashop/<папка>/<год>/`; ответ — JSON `{success, data: {path, width, height}}`. Значение поля — путь относительно сайта (без служебного хвоста, как у штатного Media). Настройка `image_convert_webp` по умолчанию выключена; при включении новые JPEG/PNG и поддерживаемые GD форматы AVIF записываются через `Joomla\CMS\Image\Image` как WebP качества 82 с проверкой MIME и размеров результата. SVG остаётся векторным, GIF сохраняет анимацию, BMP не декодируется этим API и остаётся без изменений. Ранее загруженные изображения обрабатываются отдельной командой `ImageWebpMigration`: ссылки в `products.cover`/`params`, `categories.params`, `field_media.path` и параметрах компонента меняются порциями; исходники сохраняются.

Оформление: пунктирная область с иконкой и подсказкой (допустимые форматы и предел размера), подсветка при перетаскивании, предпросмотр внутри области; под ней строка «путь к файлу (только чтение) · Выбрать с сервера · ×». Цвета берутся от текста темы (`currentColor`) и цвета ссылок шаблона, поэтому одинаково выглядят в светлой и тёмной теме (переменных Bootstrap 5.3 в Atum нет — на них не опираемся).

Вставка из буфера обмена: щелчок по области ставит на неё фокус (окно выбора файла не открывается), затем Ctrl+V / ⌘V загружает картинку из буфера (скриншот получает имя `clipboard-<время>.png`); есть и кнопки-ссылки «выберите на устройстве» и «вставьте из буфера» (Clipboard API, браузер может спросить разрешение).

«Выбрать с сервера»: внутри поля спрятано штатное поле Media (подкласс `MediaField` открывает его `getInput()`), кнопка нажимает его «Выбрать» и следит только за **значением** штатного поля, копируя выбранный путь; поэтому внутреннее устройство окна выбора файла Joomla (и его смена между версиями) нас не касается. Загрузка — перетаскиванием на область, кликом по области (выбор файла на устройстве).

Удаление файлов (`Image\ImageCleaner`, вызывается при удалении записи): удаляются **только файлы внутри `images/dharmashop/`** (загруженные нашим загрузчиком; выбранные с сервера из других папок, например `images/sampledata`, не трогаются никогда) и **только если на них больше не ссылается** другая запись магазина (категория/бренд/бейдж — по `params.image` и `params.icon`, фото товара, обложка). Замена или снятие картинки в форме файл не удаляет (вопрос F11).

Папка загрузок и структура — тоже в настройках, вкладка «Изображения»: `image_base_folder` (внутри `images/`, латиница/цифры/`-`/`_`, вложенность через `/`, пусто → `dharmashop`; `ImageUploader::baseFolder()`) и `image_folder_mode` — `type_year` (по умолчанию: `<папка>/<тип>/<год>/`), `type_year_month`, `type`, `flat`. `ImageCleaner` удаляет файлы внутри текущей папки загрузок и прежней `images/dharmashop/` (если папку меняли); остальное не трогает никогда. Уже загруженные файлы не переносятся.

Имя сохранённого файла задаётся в настройках магазина, вкладка «Изображения» (C15): `image_name_mode` — `random` (по умолчанию, 10 символов hex), `original_random` (читаемое имя + хвост), `original` (транслитерация исходного, при совпадении добавляется номер); `image_name_prefix` — необязательный префикс (латиница, цифры, `-`, `_`). Так у разных категорий и товаров не бывает одинаковых имён (в том числе у вставленных из буфера картинок).

Защита загрузки (токен CSRF, права, белые списки расширений и папок, проверка содержимого, очистка SVG, запрет исполнения скриптов в папке загрузок, фильтр путей полей) и порядок проверки — `docs/SECURITY.md`, повторяемый прогон — `dev/security-upload.sh`.

Галерея товаров (этап 2) использует тот же загрузчик в строках подформы.

### 5.16 Правила оформления заказа и их наследование (реализовано на этапе 1, C14)

`Order\OrderRules`: `forCategory($catid)` / `forProduct($product)` возвращают итоговые значения и источник каждого (товар, номер категории, `settings`, встроенное). Цепочка: поля `products.*` (NULL = наследовать) → `params.order_*` основной категории → её родители (до корня) → настройки компонента `order_*` → встроенные (мин. 1, шаг 1, макс. 0, единица «шт», учёт остатков и продажа без остатка выключены). Поле единицы измерения — выпадающий `Field\UnitField`: содержит стандартные штуки/комплекты, длину, площадь, объём, массу и время; магазин может полностью заменить список в настройке `order_units_custom` (по одной единице на строку, до 20 символов). Если старое значение больше не входит в список, оно временно добавляется к вариантам, чтобы его можно было сохранить или заменить без потери данных. Количество и единица вычисляются при чтении, поэтому изменение категории или настроек не требует пересчёта. **Учёт остатков и «продавать без остатка» материализуются** в `variants.stock_tracked/backorder` (от них зависит `in_stock` в списках): при сохранении товара, при сохранении категории (ветка, до 5000 товаров, `materializeCategories` → `rebuildCategories` → `refreshCategories`) и командой обслуживания «Пересчитать цену и наличие». Подсказки «Наследуется: …» ставит `Order\InheritHints` через `preprocessForm` (в форме категории — без её собственных значений, у новой — по родителю).

### 5.17 SEO записей (админские формы категорий, брендов, бейджей и товаров)

Поля вкладки «SEO» (формы `category.xml` и `product.xml`, fieldset `seo`): `params.meta_title`, `metadesc`, `params.meta_image`, `metadata.robots`, `params.breadcrumb_title`, `params.h1`. `Seo\SeoSettings` отдаёт шаблон типа страницы из настроек компонента (`seo_<тип>_<title|description|h1|breadcrumb>`, пока не сохранялись — встроенные), `robots()`, `openGraph()`, `defaultImage()`; `Seo\SeoHints` подставляет в пустые поля формы подсказку «Из компонента (шаблон)». В товарах `params` и `metadata` декодируются в массивы при загрузке и сериализуются `ProductTable::bind` при сохранении. Ссылки SEO-изображений товаров и категорий учитывает `ImageCleaner`. Подстановка значений в шаблон и вывод тегов — в сайтовой части (виды категорий, брендов, бейджей, товара): приоритет «поле записи → шаблон из настроек»; для картинки «поле записи → изображение записи → изображение по умолчанию».

**Цены категории** (`{category.price_min}`, `{category.price_max}`) отдельно не хранятся и не пересчитываются: у каждой строки индекса `product_categories` уже лежит актуальная копия цены `price_sort` (её обновляют сохранение товара, `SaleScheduler` и команда «Пересчитать цену и наличие»), а `SELECT MIN(price_sort), MAX(price_sort) … WHERE catid = ? AND state = 1` по индексу `idx_l_price` выполняется без чтения таблицы (`Select tables optimized away`, проверено EXPLAIN с `FORCE INDEX`; время не зависит от числа товаров). Индекс-подсказку `FORCE INDEX (idx_l_price)` указывать в запросе; результат кэшировать на время запроса страницы (на уровне Joomla — меткой категории). Число товаров категории — запрос с потолком (как счётчики листинга).

### 5.18 Страница корня раздела (реализовано, C17)

`RootController` (`root.edit/apply/save/cancel`), `RootModel` (`FormModel`), вид и шаблон `root`, форма `forms/root.xml` (структура полей как у категории, поэтому `SeoHints`, справочник шорткодов и вкладка SEO работают без изменений). Данные — JSON в параметрах компонента: `root_category`, `root_brand` (`title`, `description`, `metadesc`, `metadata.robots`, `params.{image, image_alt, introtext, meta_title, meta_image, breadcrumb_title, h1}`); сохранение — запись параметров расширения (`#__extensions`) с очисткой кэша, права `core.edit` компонента, токен формы. Пока пусто — действуют названия по умолчанию («Каталог», «Бренды») и шаблоны SEO из настроек. Сайт при выводе корня раздела читает эти параметры. Подстановки на странице корня: `{category.title}` / `{brand.title}` (название раздела), глобальные; цены и счётчики по всему каталогу — позже.

### 5.19 Список товаров в админке (реализовано)

`ProductsModel`: фильтры — состояние, основная категория (`products.catid`), дополнительные категории (прямая привязка, не основная), бренд (через индекс: у вложенных брендов находятся товары дочерних), бейдж, «все категории» (индекс, с подкатегориями), наличие; сортировки — состояние, название, артикул, категория, цена, наличие, дата, ID. Поиск: вхождение в название, алиас, артикул любого варианта; префиксы `id:`, `title:`, `alias:`, `sku:` — точное совпадение; фраза в кавычках — точное совпадение с названием, алиасом или артикулом. Привязки строк страницы (основная и дополнительные категории, бренд, бейджи) подгружаются **одним запросом по id товаров страницы** из `product_categories`. Колонки: состояние, изображение, название (алиас и артикул), категории (основная жирным, дополнительные, бренд, бейджи цветом), цена, наличие, ID. Панель фильтров открыта сразу. Отметки «избранный» в товаре нет (C18): акценты — бейджи.

### 5.20 Галерея товара (реализовано, C19)

Поле формы `gallery` (`Field\GalleryField`, `media/js/gallery.js`, `css/gallery.css`; активы `com_dharmashop.gallery`). Значения — `jform[gallery][N][path|alt|title]`; служебное `gallery[_][path]` гарантирует, что ключ придёт в запрос, когда все изображения убраны. Серверный фильтр `ImageUploader::filterGallery` (Registry превращает ассоциативные массивы формы в объекты — фильтр это учитывает): пути через `filterPath`, повторы и пустые отбрасываются, подписи очищаются от тегов, не более 100. `GalleryRepository` (`ShopServices::gallery()`) в транзакции сохранения товара заменяет строки `field_media` (`field_id = 0`) и пишет `cover` / `cover_alt` из первого изображения; при удалении товара галерея чистится, а файлы удаляются через `ImageCleaner` после фиксации. Нарезка размеров (миниатюры, F10) — позже; пока список показывает исходный файл, уменьшенный стилем.

Уточнение 2026-10-08: в редакторе у фотографии одна строка «Описание фото» (`alt`); фильтр при отсутствии ключа `title` копирует в него очищенный `alt`. Прежние данные с двумя ключами фильтруются без изменения. В форме и на витрине приоритет: непустой alt → прежний title; на витрине при пустом описании — название товара. Модель передаёт одно описание в alt и подпись лайтбокса. При следующем сохранении новой формы оба столбца получают единый текст; схема и массовые данные не меняются.

Боковая колонка формы категории (и бренда, бейджа) оформлена так же: рамки «Основное» (родитель, состояние), «Шорткоды», «Сведения» (тип, ID), «Количество» (товары, цена от и до) и «Доступ» (доступ, заметка). Боковая колонка формы товара — рамки (fieldset): «Категории» (основная, дополнительные, бренд, бейджи), «Шорткоды» (кнопка окна справочника, `Seo\PlaceholderPanel` — общий для товара, категории и корня; в SEO общих настроек тот же каталог кодов выводит `ShortcodesField` справа от полей, без кнопки/окна/колонки «Сейчас» и без сохраняемого значения. Bootstrap-раскладка только этой вкладки задаётся существующим settings-search.js), «Основное» (ID, состояние) и «Публикация» (создан, автор, изменён) внизу; вкладки «Основное» (галерея, краткое описание — редактор, полное описание), «Цена и остаток», «Оформление заказа», «Метаданные», «Права доступа».

### 5.21 Скидка вместо акционной цены (реализовано, C20)

Скидка вводится одной строкой: «500» (сумма) или «10%» (проценты); запятая допустима (`250,5`). `Pricing\Discount`: `parse` (ввод → значение и признак процентов), `assertValid` (проценты меньше 100, сумма меньше цены, без цены скидки нет), `apply` (итог не ниже нуля, два знака), `format`. Ошибки проверяются **до записи карточки**. В `variant_prices` значение и признак процентов; `price_final` — итог по `PriceResolver` на момент сохранения, а по датам «скидка с/по» его пересчитывают `SaleScheduler` (одним набором запросов) и команда «Пересчитать скидки по датам». В списке товаров при действующей скидке у товара с одним вариантом: в первой строке текущая цена жирным, ниже в одну строку зачёркнутая старая цена и красный значок размера скидки («−500 ₽» или «−10%»); по нескольким вариантам — «от …». Подстановки: `{product.price}` (с учётом скидки), `{product.price_old}`, `{product.discount}`.

В форме товара после поля «Скидка» показывается только для чтения «Цена со скидкой»: итог и в скобках размер скидки («500 ₽ (−2 500 ₽)»), а если скидка не действует по срокам — пояснение. Значение считается при загрузке формы (обновляется после сохранения; без своего JS в админке живого пересчёта при вводе нет).

### 5.22 Мини-корзина, цены витрины и пункт меню «Товар» (реализовано, C67/C68)

Страница товара: `default.php` размещает три Bootstrap-колонки (xl: 4/5/3): галерея, краткое описание/параметры, покупка; ниже xl покупка следующей строкой, ниже md все блоки по одному. Подключает `default_gallery.php` и единый `default_buy.php` через `loadTemplate`. Интро проходит `Product\HtmlView::text()` и общий Renderer (однократные подстановки, HTML значений экранируется); `{product.introtext}` из ProductValues доступен в SEO/текстах и обоих справочниках. Его нормализация общая с админским предпросмотром: текст без HTML/сущностей до 200 символов, без SQL. Блок покупки содержит цену/скидку/таймер акции, наличие/артикул и действия покупки, переопределяется независимо в шаблоне сайта. Оформление — Bootstrap card: наличие/артикул в шапке выше цены, цена и действия в теле; actions получает `stackActions=true` для вертикальной раскладки в узкой колонке.

Уточнение 2026-10-08: `CartService::add()` дополнительно возвращает `added` — компактный снимок добавленного товара/варианта и добавленное количество (не общий итог строки). Обложка/название варианта читаются тем же запросом по ID, без дополнительного обхода каталога. JSON контроллера дополнен `notification: null | {type, html}`. Только успешное добавление при `cart_notification_type=toast` рендерит `components.dharmashop.cart.toast`; ошибка макета журналируется и не отменяет добавление. `cart_notification_seconds` — 0–300 с (5 по умолчанию, 0 — вручную). Клиент передаёт notification через прежнее `dharmashop:cart-updated`, отдельный ассет `com_dharmashop.cart-toast` использует `bootstrap.toast`. Остальные виды уведомлений можно добавить значением списка и своим обработчиком. Реестр макета/контекста — LAYOUTS.md / IMAGE_RESIZE.md.


- **Модуль `mod_dharmashop_cart`:** значок (число позиций, сумма) и боковая панель (offcanvas Bootstrap, переносится в `body`). Содержимое панели — фрагмент `view=cart&layout=mini&format=raw&mid=<модуль>` (`Cart\RawView`: настройки показа читает у опубликованного модуля), разметка — шаблон модуля `tmpl/default_items.php` (фрагмент отдаёт компонент, `RawView::loadTemplate` подключает файл модуля с учётом переопределения шаблоном сайта), стили `media/mod_dharmashop_cart/css/minicart.css` (ассет `mod_dharmashop_cart.minicart`, отключаются настройкой модуля). Настройки модуля: сумма, действие по клику, открытие после добавления, фото, категория, артикул, цена за единицу, скидка (зачёркнутая обычная цена), кнопка «Оформить заказ» и классы кнопок, подключение стилей.
- **Модуль на чекауте:** при разрешённом маршруте `com_dharmashop` / `checkout` диспетчер передаёт `cartDisabled=true`, `offcanvas=false`, `openOnAdd=false`. Значок — статичный `span` без ссылки; панель и её ассеты не подключаются. Состав меняется на странице корзины, возврат к ней доступен из сайдбара чекаута.
- **Строки корзины** (`CartService::lines`) отдают итоговые правила товара: единицу, минимум, шаг, максимум (`OrderRules::forProduct` — товар → категория → настройки), название категории и обычную цену.
- **Цены витрины:** `MoneyFormatter::format()` — число с валютой (админка, заказы), `price()` / `range()` — для витрины: при включённой настройке `price_zero_replace` нулевая цена заменяется текстом `price_zero_text` (по умолчанию «Цена по запросу»); применяется в карточках, на странице товара, в подстановках, корзине и значке модуля. Покупка нулевого товара разрешена. Пустая цена в форме товара при сохранении записывается как 0 (покупать можно). Нулевая цена показывается текстом «Цена по запросу» (замена включена) либо «Бесплатно», если у товара или его категории включено «Цена 0 — бесплатно» (`OrderRules::priceIsFree`, наследование как у остальных правил; `MoneyFormatter::price/range($free)`); признак сохраняется в `order_items.params.zero_free`, чтобы заказ и письма оставались прежними при смене правила. Итог корзины со строками без цены: «сумма + текст».
- **Пункт меню «Товар»:** `tmpl/product/default.xml`, поле `modal_product` (`Field\Modal\ProductField`, окно `products&layout=modal`). Страница товара при собственном пункте меню берёт SEO из пункта и не дописывает крошки.

## 6. События

Уточнение C77/C79: HtmlTemplate встраивает локальные изображения письма через PHPMailer addEmbeddedImage (MIME inline/CID), включая SHOP_LOGO. Сначала ImageUrl получает производные email.product/email.logo (JPEG/PNG); вложения ограничены 256 КиБ на файл и 2 МиБ суммарно. Тяжёлый резервный оригинал не прикрепляется. Проверяются host/port, базовый путь и realpath внутри JPATH_ROOT. Внешние URL не скачиваются.

Имена свои, префикс `onDharmaShop`. Каждое — типизированный класс события, аргументы читаются геттерами, изменяемое — через сеттеры. Подписчики — `SubscriberInterface`. Группа плагинов загружается по назначению (`PluginHelper::importPlugin`).

### 6.1 Каталог (группа `dharmashop`)

| Событие | Когда | Что можно менять |
|---|---|---|
| `onDharmaShopImagePrepare` | C79: перед выводом изображения через `Administrator\Image\ImageUrl::resolve`; сайт/админка/email | Joomla Event, `request: ImageRequest` (source/context readonly, url изменяемый), `settings: Registry` (копия). Плагин dharmashop/imageresize создаёт производную без SQL каталога; нет обработчика — исходный URL. |
| `onDharmaShopProductBeforeSave` / `AfterSave` | сохранение товара вместе с вариантами и полями (форма и CLI) | данные; отмена с ошибкой |
| `onDharmaShopProductBeforeDelete` / `AfterDelete` | удаление | отмена |
| `onDharmaShopVariantBeforeSave` / `AfterSave` | сохранение варианта | данные; отмена |
| `onDharmaShopProductPrepareForm` | сборка формы товара (поверх `onContentPrepareForm`) | вкладки, поля |
| `onDharmaShopListingQuery` | перед запросом листинга на сайте | условия, сортировки, join |
| `onDharmaShopProductPrepare` | карточка или плитка готовится к выводу | бейджи, данные, блоки |
| `onDharmaShopProductDisplay` | не подключён; вывод страницы использует именованные события ProductBefore/After… и ProductTabs ниже | — |
| `onDharmaShopCategoryBeforeTitle` / `CategoryAfterTitle` / `CategoryBeforeContent` / `CategoryAfterContent` | C31: HTML вокруг заголовка и всего контента категорий, брендов и бейджей (`categories`, `category`, `brands`, `brand`, `badges`, `badge`) | `LayoutRenderEvent`: `entity`, `view`, `position`, `item`, `items`, `params`, `page`; строки через `addResult()` |
| `onDharmaShopProductBeforeTitle` / `ProductAfterTitle` / `ProductBeforeContent` / `ProductAfterContent` | работают на публичной странице товара | Тот же контракт, `entity=product`, `view=product`, `items=[]` |
| `onDharmaShopProductBeforeGallery` / `ProductAfterGallery` | до/после блока Gallery на странице товара (2026-10-08) | LayoutRenderEvent, data=[]; точные места — SHORTCODES.md |
| `onDharmaShopProductBeforeIntro` / `ProductAfterIntro` | до/после блока Intro на странице товара (2026-10-08) | LayoutRenderEvent, data=[]; точные места — SHORTCODES.md |
| `onDharmaShopProductBeforeIntroFields` / `ProductAfterIntroFields` | до/после блока IntroFields на странице товара (2026-10-08) | LayoutRenderEvent, data=[]; точные места — SHORTCODES.md |
| `onDharmaShopProductBeforeBuy` / `ProductAfterBuy` | до/после блока Buy на странице товара (2026-10-08) | LayoutRenderEvent, data=[]; точные места — SHORTCODES.md |
| `onDharmaShopProductBeforePrice` / `ProductAfterPrice` | до/после блока Price на странице товара (2026-10-08) | LayoutRenderEvent, data=[]; точные места — SHORTCODES.md |
| `onDharmaShopProductBeforeActions` / `ProductAfterActions` | до/после блока Actions на странице товара (2026-10-08) | LayoutRenderEvent, data=[]; точные места — SHORTCODES.md |
| `onDharmaShopProductBeforeTabs` / `ProductAfterTabs` | до/после блока Tabs на странице товара (2026-10-08) | LayoutRenderEvent, data=[]; точные места — SHORTCODES.md |
| `onDharmaShopProductBeforeDescription` / `ProductAfterDescription` | до/после блока Description на странице товара (2026-10-08) | LayoutRenderEvent, data=[]; точные места — SHORTCODES.md |
| `onDharmaShopProductTabs` | панели плагинов после стандартных вкладок, в том числе без описания/групп | LayoutRenderEvent, data.tabset; панели UITab через addResult |
| `onDharmaShopPriceResolve` | расчёт эффективной цены варианта | цена (группы, акции, скидки) |
| `onDharmaShopStockChange` | любое движение остатка | — |
| `onDharmaShopIndexRebuild` | пересборка индекса и поиска товара | дополнительные данные индекса |

Точки вывода C31: группа `dharmashop`. `Site\Support\LayoutHooks` импортирует плагины и отправляет `Site\Event\LayoutRenderEvent` (AbstractImmutableEvent + ResultAware + ResultTypeStringAware) через диспетчер Joomla, объединяет строки результатов. `item` — текущая запись (у корня категорий виртуальный id=0), `items` — показанные записи списка, `params` — копия разрешённых параметров отображения, `page` — PageMetadata либо null; `view` и `position` позволяют плагину выбрать нужную страницу/зону. Аргументы нельзя заменять, HTML добавляется `addResult()`, обычный return/echo не является контрактом. Вызовы только в макете страницы, не в каждой карточке; вокруг заголовка даже при скрытом h1. До/после контента охватывают изображение, интро, список и описание. Вывод без обёрток, свои блоки и экранирование обеспечивает плагин; механизм не делает SQL. Реестр и пример — SHORTCODES.md §3. Виды category/product передают проверенные записи. LayoutHooks::render дополнен необязательным data=[]; Product HtmlView::renderHook — общий вызов для частичных шаблонов. Без обработчиков дополнительные HTML-обёртки не создаются.

### 6.2 Поля (группа `dharmashopfield`)

| Событие | Назначение |
|---|---|
| `onDharmaShopFieldTypesCollect` | регистрация типов (id, подпись, `store`, XML настроек) |
| `onDharmaShopFieldFormElement` | XML-элемент редактирования значения |
| `onDharmaShopFieldValueEncode` / `Decode` | значение формы ↔ строки хранилища |
| `onDharmaShopFieldFilterElement` / `FilterApply` | элемент фильтра и условие запроса |
| `onDharmaShopFieldRender` | вывод значения на сайте |

Уточнение реализации C53 к §6.2: сейчас действует только `onDharmaShopFieldTypesCollect` (Joomla Event, аргумент `registry: Administrator\Fields\TypeRegistry`). Плагин вызывает `registry->register(TypeInterface)`, после dispatch регистрация закрыта. Контракт `TypeInterface` относится к определениям: `id/label/store/settingsPath/normalizeSettings`. `dharmashopfield/basic` регистрирует шесть типов и XML настроек; runtime-события формы/значений/фильтра/вывода из таблицы выше ещё зарезервированы. C59 добавил `ValueTypeInterface` (`input/defaultValue/decode/encode`) к зарегистрированному типу: это методы провайдера, отдельные runtime-события не вызываются. Диапазон использует num/num_end; прочие зарезервированные события ещё не реализованы.

### 6.3 Корзина и оформление

| Событие | Что можно менять |
|---|---|
| `onDharmaShopCartBeforeAdd` / `AfterAdd` | отклонить позицию, дополнить `params` |
| `onDharmaShopCartItemValidate` | ошибки количества, наличия, кратности |
| `onDharmaShopCartTotals` | добавить строки итогов |
| `onDharmaShopCheckoutPrepareForm` | поля оформления (поверх `onContentPrepareForm`) |
| `onDharmaShopCheckoutValidate` | дополнительные проверки |
| `onDharmaShopShippingMethods` / `ShippingQuote` | доступные способы доставки, цена и срок (считает плагин доставки) |
| `onDharmaShopPaymentMethods` | доступные способы оплаты |

### 6.4 Заказ и оплата

| Событие | Назначение |
|---|---|
| `onDharmaShopOrderBeforeCreate` / `AfterCreate` | Before — план; After есть C76 после commit: order_id/history_id, группа dharmashop. Повтор checkout не вызывает. |
| `onDharmaShopOrderStatusChanged` | C76: после commit, order_id/history_id/previous_status_id/status_id, группа dharmashop |
| `onDharmaShopPaymentStart` | плагин формирует ссылку/форму платежа |
| `onDharmaShopPaymentCallback` | входящее уведомление платёжной системы (идемпотентно по `external_id`) |
| `onDharmaShopNotifyCollectChannels` / `OrderNotify` | CollectChannels — план; OrderNotify есть C76: notification (readonly OrderNotification), settings (Registry), retry (bool). Все каналы, включая email, — плагины dharmashopnotify. Исключение канала не отменяет commit заказа. |

### 6.5 Служебные

| Событие | Назначение |
|---|---|
| `onDharmaShopMetadataReady` | C28/C29: после базового SEO, до рендера HTML. Joomla Event: `page` (readonly `Site\Seo\PageMetadata`), `document` (`HtmlDocument`), `settings` (`Registry`), `content` (readonly `Site\Seo\CollectionMetadata`, `Site\Seo\ProductMetadata` или `null`). Контекст списка: `name`, `items` (`name`, абсолютный `url`) в порядке карточек, `itemType` (`CollectionPage` для разделов, `Product` для товаров). Группа плагинов `dharmashop`; `plg_dharmashop_social` добавляет Open Graph/Twitter Cards; `plg_dharmashop_structureddata` — JSON-LD. Запросов каталога в плагинах нет. |
| `onDharmaShopSettingsPrepareForm` | вкладки настроек от плагинов |
| `onDharmaShopAdminToolsCollect` | ссылки расширений на панели инструментов |

События этапов 2+ проектируются по мере реализации; имена из списка резервируются сейчас. Статусы, подстановки `{область.имя}` и шорткоды — в `docs/SHORTCODES.md`.

## 7. Админка и сайт

### Поиск по настройкам (C72)

Непостоянное поле `_settings_search` (`SettingssearchField`) в config.xml регистрирует JS `com_dharmashop.settings-search`. Скрипт размещает поиск перед штатными вкладками com_config и индексирует названия/описания/путь полей готовой формы, включая созданные строки subform. Результат открывает вкладку нативным activateTab и прокручивает/подсвечивает поле; для скрытого showon показывает условия и переводит к видимому управляющему полю. Значения настроек не меняются, у input поиска нет name, запрос не сохраняется. Joomla и шаблон админки не переопределяются, CSS не добавляется. Подробнее — [`SETTINGS_SEARCH.md`](SETTINGS_SEARCH.md).

### 7.1 Меню админки

В административном списке товаров бейдж «В наличии» дополнен количеством для простого товара с включённым учётом остатков: ProductsModel читает stock_total/stock_tracked основного варианта из существующего JOIN, макет форматирует до трёх знаков без конечных нулей. Без учёта остатков и для товаров с несколькими вариантами количество не показывается; отсутствие и фильтры сохраняют прежнюю логику.

На странице настроек Dharma Shop скрипт C72 скрывает внутреннюю навигацию com_config по компонентам и расширяет #config классом col-12. Основное меню Joomla остаётся штатным. Изменение ограничено #component-form и компонентным полем поиска, без правок com_config/шаблона и CSS.

Заказы · Корзины · Покупатели · Товары · Категории · Бренды · Бейджи (три пункта на один наш вид `categories` с параметром `extension=com_dharmashop`, `.brand`, `.badge`) · Порядок товаров · Поля · Группы полей · Статусы заказов · Способы доставки · Способы оплаты · Настройки (штатный `com_config`). «Аналитика» — отдельный компонент. Прайс-листы и склады в меню в v1 не выводятся (в БД они есть).

Списки — `searchtools`, сортировка по столбцам, штатная постраничность и нативные Joomla-окна пакетной обработки. XML-блоки соответствуют `$batch_commands`; пустые блоки удаляются до вызова `AdminModel::batch`. Товары меняют группу/таксономию; категории, бренды и бейджи — доступ, родителя и параметры вывода. Группы меняют собственную таксономию транзакционно, не переписывая участников. Отдельная команда цен изменяет цену, скидку и период только у отмеченных товаров, затем перестраивает производные цены/наличие, группы, индекс категорий и кэш фильтра; sparse selection пересчитывается по точному списку ID, а не по широкому диапазону. Подробная матрица действий — [`BATCH_PROCESSING.md`](BATCH_PROCESSING.md).

Форма товара — вкладки (`uitab`): «Основное» (название, алиас, категории, описание), «Цена и остаток» (для одного варианта) или «Варианты» (подформа), «Поля» (поля товара, в том числе галерея), «Отображение» (макет страницы товара), «SEO», «Публикация».

### Реестр макетов витрины

Полный реестр слотов, папок и стадий готовности поддерживается в [`LAYOUTS.md`](LAYOUTS.md). `Field\LayoutField` сканирует `.php`-файлы установленного сайта в соответствующей папке и выводит имя файла в нативный select; сохраняется имя без суффикса `.php` в `params.product_layout`, `params.category_layout`, `params.product_card_layout` или параметрах компонента. Персональные значения хранятся в существующем JSON `params`; схема БД не меняется. Для списка категорий уже реализованы выбор и применение файлов `categories_layout` / `category_card_layout`; остальные виды остаются заглушками. Новые пункты появляются автоматически при установке нового PHP-файла в слот. Имена административных файлов из `administrator/components/.../tmpl` не используются.

### 7.2 Права

`access.xml`: штатные `core.*` + `dharmashop.orders.manage`, `dharmashop.orders.status`, `dharmashop.customers.view`, `dharmashop.settings`.

### 7.3 Сайт

Виды: пункты меню `categories` (список категорий), `category` (товары выбранной категории), `brands` и `badges`; также `product`, отдельная страница `brand`, страница товаров по бейджу `badge`, `search`, `cart`, `checkout`, `order` (по `secret`), `orders` (кабинет). Страницы категорий и минимальная оболочка товара уже выводятся; товар пока показывает заголовок и хуки, наполнение будет отдельным этапом. Маршрутизатор — `RouterView` с `MenuRules`/`StandardRules`/`NomenuRules`. Переопределения — в `dharma_starter` (`html/com_dharmashop`), оформление только классами Bootstrap без CSS компонента. Корзина работает без JS (формы POST с редиректом), JS — только улучшение.

**Адреса (C40).** `Site\Service\Router` (`RouterView` + `MenuRules`, `StandardRules`, `NomenuRules`): виды `categories` и `category` вложенные, в адресе только алиасы (`/tovary-kategorii/stoly/uncategorised`); сегмент разбирается по алиасу внутри родителя (`#__categories`, `extension = com_dharmashop`, `parent_id`, `alias`), цепочка строится от пункта меню вида (корень — id 0 или 1). Старые адреса с `option/view/id` (`QUERY_STRING`) перенаправляются 301 на ЧПУ в `HtmlView::redirectLegacyUrl()`.

**Список товаров категории (C41, уточнение 2026-10-12).** Общая подготовка страницы и фрагмента — `View\Category\ListingTrait`; `HtmlView` выводит страницу/SEO, `RawView` — `default_items.php` с `X-Robots-Tag: noindex, follow`. `listing.js` меняет вид фрагментом без смены URL и текущего `start`. Выбор вида хранится в sessionStorage вкладки; при новой странице восстанавливается фрагментом, если серверный вид отличается. Нативные ссылки `?start=N` остаются в HTML; обработчик JS убирает `product_view` и сортировку по умолчанию из адреса перехода. `dharmashop:navigate` остаётся отменяемым событием с `detail.url`; при `pagination_type=ajax` его перехватывает `pagination.js` (замена `#dharma_shop`, head и history). `loadmore.js` в more/infinite хранит смещения порций в `data-dharmashop-loaded-pages` контейнера; смена вида заново рендерит эти порции, объединяя сетку или tbody и сохраняя навигацию последней. Одновременно выполняется до четырёх запросов; SQL остаётся постраничным, серверный лимит не расширяется. Устаревший ответ догрузки не вставляется в заменённый список. AJAX-фильтр очищает массив смещений перед подменой списка, поэтому старые порции не восстанавливаются в новых результатах. После обновления остаётся `dharmashop:updated`. Смена сортировки сбрасывает накопленный список и переходит к первой странице. Без JS GET-формы/ссылки работают с явными параметрами. `sort`/`product_view` в прямом GET — noindex, follow; canonical непоисковой/нефильтрованной выдачи сохраняет текущий `start`, убирая параметры сортировки и вида. Фильтр использует свою настройку canonical.

**Карточка товара и переход на товар (C46).** Все карточки проходят через `layouts/components/dharmashop/product-card/default.php`; этот диспетчер подключает вариант `grid`, `list` или `table` в выбранном слоте (`product-card`, `brand-product-card`, `badge-product-card`). `product-card/grid.php` связывает с `view=product&id=<id>` изображение и заголовок; карусельные кнопки и бейджи остаются отдельными интерактивными элементами. До реализации алиасного маршрута используется SEF-адрес с ID. Для страницы существует публичный `ProductModel` с проверкой публикации товара, доступа к категории и её предков, `View\Product\HtmlView` применяет SEO и рендерит `tmpl/product/default.php`.

**Вывод скидки (C47).** В настройках «Корзина» отдельные ключи для списка (`product_card_show_discount`, `product_card_discount_display`) и страницы товара (`product_page_show_discount`, `product_page_discount_display`). Форматы `amount`/`percent`, стандартный вид — процент. Выключатель скрывает старую цену и индикатор скидки. Для списка величина скидки пакетно считается из `variant_prices.price - price_final`; процент не округляется в модели и выводится до двух знаков, значения ниже точности показываются как `<0,01%`. На странице товара используется цена варианта по умолчанию (`price_list_id=1`).

**Таймер акции и группы настроек (C48).** Настройки под общей вкладкой «Корзина» группируются по месту вывода: `cart_product_card` («Карточка товара в списке») и `cart_product_page` («Страница товара»). В каждой группе живут свои флаги скидки, формат, количества и `*_sale_countdown` (включён по умолчанию); подпись кнопки корзины общая. Отдельные группы страницы корзины и модуля корзины добавляются здесь, когда появятся их настройки. В карточке таймер дописывается в бейдж с ID настройки `sale_badge_id` (пример: `Акция · до конца 22 дн. 4 ч.`) и наследует его Bootstrap размер, форму и цвет. Если бейдж акции не назначен или скрыт, показывается отдельный бейдж `До конца акции: …` с тем же оформлением. На полной странице таймер стоит рядом с ценой, отдельным бейджем; видимая метка короткая, полная подпись находится в `aria-label` и подсказке. Модель выдаёт Unix timestamp конца только для активной скидки с будущим `discount_end`. `variant_prices.discount_start/end` заданы в UTC, `Site\Support\SaleCountdown` проверяет активное окно по UTC, а `media/js/sale-countdown.js` обновляет отображение дней/часов раз в минуту и прячет бейдж после истечения срока. В списках данные конца акции загружаются тем же пакетным запросом цен; `WebAssetManager` подключает скрипт только при включённом таймере и подходящей акции.

Поля выбора раздела в типах меню `categories`, `category`, `brands` используют штатный `CategoryField` с `layout=joomla.form.field.list-fancy-select`: поиск через Joomla Choices, дерево категорий соответствующей секции, без собственного JS/CSS. `show_root=true` добавляет «Корень» со значением `id=0`, в том числе для товаров категории (весь каталог). Это виртуальный корень из C17, отдельная категория БД не создаётся. C34 подключил root_category и данные товаров всего каталога к каркасу страницы; карточки пока пусты.

Решение C33 задаёт оформление по умолчанию для товаров бренда и бейджа: шаблон «Товары категории» и общий layout карточки товара. Переключатель общего оформления будет включён по умолчанию; отключение позволит выбрать самостоятельные файлы, первоначально оформленные как наш базовый список. Базовая страница подключена по C34, три карточки пока пустые; переключатель и применение на страницах бренда/бейджа ещё впереди. Общие файлы не объединяют данные и SEO разных разделов; список категорий имеет отдельные шаблон и карточку.

**C34:** `Site\Model\CategoryModel`/`Site\View\Category\HtmlView` подключены к странице товаров. Корень читает root_category, прямые подкатегории — общую модель CategoriesModel; доступ/публикация проверяются по родителям. Шаблон содержит заголовок, изображение/интро, подкатегории, GET-панель сортировки и режима, итоги по переключателям, контейнер товаров и описание. Карточки grid.php/list.php/table.php пока пустые по просьбе пользователя; внешняя сетка/список/таблица подготовлена. Settings → родители → запись → меню, непустые переопределения; GET sort/product_view проходит allowlist. Сортировка и режим сохраняются в запросе, после смены начинается первая порция. Четыре события Category, общий PageSeo и социальные плагины подключены; товарная JSON-LD отложена до содержимого карточек.

Получение товаров: ограниченная порция по ключу (колонка сортировки, id), after — ID доступного граничного товара; без OFFSET, JOIN по индексам/PK. Для конкретной категории таблица product_categories, для всего каталога products (один товар один раз), ручной порядок корня — ID. Поддержаны nullable цены при обеих сортировках и стабильное разрешение равенства по ID. Общие COUNT/MIN/MAX запрашиваются только при включённых флагах итогов. Проверяется основная категория и её предки, SQL отсутствует в шаблонах. Масштабирование корневых сортировок по цене/названию и запросов доступа пока не измерено; схема БД не менялась. Переходы порциями «Далее/В начало» подготовлены, полный пагинатор и фильтры впереди.

**C35:** единый реестр `media/com_dharmashop/joomla.asset.json`; отдельные именованные ресурсы и зависимости. Новый `js/listing.js` включён только в Category HtmlView через `$wa->useScript('com_dharmashop.listing')`, после добавления реестра расширения. Автоматическая GET-отправка панели при смене вида/сортировки, без JS остаётся submit. Рендер выбранного PHP-layout выполняется сервером, AJAX пока не нужен. Код RM не читался; сверены только имена каталогов/файлов. Каждый будущий JS-файл подключать через WA нужного вида/поля, без script-тегов и inline-кода; Bootstrap используется из шаблона сайта, свой CSS фронтенда не добавляется.

**C36:** настройки товаров категории расширены `subcategory_show_image` (Да) и `category_default_view` (grid/list/table, default grid). Меню и запись категории имеют наследуемые переопределения; действуют на текущую страницу и её подкатегории. `renderSubcategory` передаёт `showImage`, карточка по false не выводит изображение/иконку. Изображение текущей страницы товаров категории управляется отдельно через `category_page_show_image`: глобальное значение по умолчанию — Нет, запись категории и пункт меню могут его переопределить. Флаг влияет только на большую картинку над содержимым страницы, `subcategory_show_image` независимо управляет изображениями карточек подкатегорий; самостоятельный список категорий сохраняет своё поведение. По C37 товарные поля теперь доступны также в формах производителей и бейджей; флаг дочерних изображений не нужен плоскому бейджу. Начальный вид разрешается из параметров, GET-выбор посетителя важнее; свойство productView определяет контейнер и переключатель, cardLayout — PHP-файл. Стандартные файлы выбираются по режиму; собственный renderer получает mode. Если новая настройка ещё не сохранена, используется прежний выбор макета как совместимый начальный вид.

**C37:** вкладка «Отображение» доступна всем трём типам записей. Форма категории разделена на видимость/дочерние категории/товары; форма производителя добавляет отдельные `brands_*`/`brand_card_*` для дочерних производителей и сохраняет общий товарный блок; у бейджа только товарный блок. Все переопределения выбора пустые по умолчанию, без смены существующих ключей и схемы. list_limit/list_ordering теперь сохраняются и у бейджа; порядок новых связей и оформление заказа остаются только в обычных категориях. Шаблон админки выводит непустые нативные fieldset, для производителя/бейджа поясняет, что применение ожидает реализации их фронтальных видов. C37 реализует формы; разрешение наследования и вывод их страниц ещё впереди.

**C38:** grid-карточка наполнена минимальной разметкой: обложка/название/интро/цена. Данные берутся из уже загруженной порции, цена — готовая строка MoneyFormatter, запросов в layout нет. Ссылки товара, корзина, list/table и товарная JSON-LD ожидают следующих шагов.

**C39:** `Site\Placeholder\Renderer` применяется к SEO-полям, интро и описанию category/categories, а также к интро товара в grid-карточке. `CategoryValues` предоставляет title/parent/короткий introtext, ленивые count и price_min/max через `CategoriesModel`; `ProductValues` использует уже загруженные title/price_min/max/unit/in_stock без запроса. `global.sitename/currency/url` централизованы в `GlobalValues` и дополняют значения в `PageSeo`. В HTML-режиме сохранённый HTML остаётся разметкой, вставки экранируются; неизвестные коды остаются видимыми. Root count считает весь опубликованный каталог с потолком; root price range пока пуст. Обновление 2026-10-08: ProductValues подключает sku/category/brand/price/price_old/discount из подготовленных данных; product.price_min/max оставлены только как скрытые алиасы совместимости; подстановки материалов — отдельная задача.

### 7.4 Модули

`mod_dharmashop_categories` (2026-10-09): непосредственные подкатегории с исключениями или выбранные категории; ограничение limit + 1, публикация/ACL предков, пакетная подготовка маршрутов `Router::primeCategories`. Верхний уровень — один запрос, вложенные — максимум ещё один на недостающих предков. Включаемые количество (до 10 000 точно) и цены берутся одним дополнительным запросом из индекса товаров с ограниченными подзапросами. Сетка без JS; аккордеон через WAM Bootstrap collapse, дочерние категории выбираются одним ограниченным пакетным запросом и выводятся карточками. Слайдер — общий Swiper через WAM и независимый инициализатор. Общие/собственные карточки сетки и слайдера, отдельные контексты изображений всех трёх макетов, кнопка каталога по доступному пункту меню. Настройки находятся в первой вкладке и показываются условно по макету. Настройки, контракт и замеры — `MODULE_CATEGORIES.md`.

`mod_dharmashop_products` (2026-10-12): новые и просмотренные товары; отбор по категориям, брендам, бейджам, значениям списков и диапазонам; И/ИЛИ между группами; выдача товаров либо представителей групп. История — cookie на 180 дней с лимитом 20 ID, без учётной записи/API; закрыта от page cache. Сетка/список/слайдер с общими или своими карточками. Необязательная дедупликация экземпляров работает только в рамках текущего запроса страницы. Изображения проходят через `ImageUrl::resolve` и контексты `module.products.*`. Спецификация — `MODULE_PRODUCTS.md`; замеры нагрузки для крупного каталога и полей ещё требуются.

`categories`, `search`, `filter`, `products`, `cart`. Фильтр-модуль и вид категории используют один `FilterBuilder`.

## 8. Письма, настройки, перенос с RadicalMart

- **Письма C76:** плагин `plg_dharmashopnotify_email` в общем пакете, четыре штатных шаблона Joomla `plg_dharmashopnotify_email.order.new.admin/customer`, `.status.admin/customer`. SMTP/отправитель — Joomla, настройки событий/получателей/содержимого — вкладка «Уведомления» магазина. Шаблоны владельца не перезаписываются обновлением. Текст статуса — `statuses.customer_message`. Системные получатели Joomla (sendEmail=1, block=0) и дополнительные email; каждому отдельное письмо. Покупателю — contact_email из снимка заказа. Отправка default=0, установка не делает рассылку/доставку старых заказов. Журнал/ручной повтор ошибок, синхронная отправка после commit; фоновой очереди пока нет. Подробности — NOTIFICATIONS.md.
- **Оформление писем C77:** `shop_identity` в первой вкладке config: название/логотип/email/телефон/адрес. Плагин наследует MailTemplate через `Mail\HtmlTemplate` для локального HTML/text-режима без изменения com_mails; транспорт, нативные редактируемые записи, событие рендера и индивидуальная почтовая конфигурация сохраняются. `Mail\MessageData` готовит скалярные теги и экранированные блоки из снимка, `tmpl/mail.php`/`blocks.php` — табличное email-оформление. NotificationService дополнительно выбирает shipping_title/payment_title, не провайдерский JSON. `CONSENTS` из сохранённого contacts.consents содержит фактические Да/Нет и имеющееся время принятия; блок у обеих аудиторий независимо от CONTACTS. Установка дополняет только прежний стандартный пустой HTML, пользовательские тексты/темы/HTML/params сохраняются. Реестр тегов — SHORTCODES.md.
- **Настройки (`config.xml`):** сейчас реализованы валюта (код, символ, позиция, знаков), число товаров на странице и сортировка по умолчанию, положение новых связей товара в категории («Первый / Последний», по умолчанию «Последний»), правила оформления заказа и список единиц измерения товара, значения по умолчанию и списки единиц веса/габаритов, изображения и SEO. Сортировки, не поддерживаемые индексом или моделью (например, рейтинг), не предлагаются. Подкатегории и счётчики фильтров, срок жизни корзины, гостевое оформление, формат номера заказа и порог курсорной пагинации добавляются вместе с реализацией соответствующих страниц и логики, чтобы не показывать неработающие переключатели.
- **Перенос с RadicalMart (только данные):** консольный плагин читает `#__radicalmart_*` (источник — копия на dharmatest, получатель — test.test), идемпотентен, ведёт соответствия в `external_ids`.

| RadicalMart (схема dharmatest) | Dharma Shop |
|---|---|
| `categories` (nested set) | штатные категории Joomla через Category Table |
| `metas` (тип variability) + `products.meta_variability` | товар с `variants_mode = 2`; товары RM, входящие в мета-товар, становятся его вариантами; оси — из `params.variability_fields` |
| `products` без мета-товара | товар + один вариант |
| `products.prices` JSON (`base`, `final`, `discount`, `discount_end`) | `variant_prices` основного прайс-листа |
| `products.stock` JSON | `stock_levels` основного склада, `stock_tracked` |
| `products.fields` JSON | `field_values` / `field_texts` (формат разобрать на этапе 1) |
| `products.media` JSON | `field_media` (поле-галерея) и `products.cover` |
| `fields` (`plugin = standard`), `fieldsets` | `fields`, `fieldgroups`, `field_options` |
| `customers`, `statuses`, `shipping_methods`, `payment_methods` | аналоги |
| `orders` (JSON `products`/`shipping`/`payment`/`total`), `carts` | `orders` + `order_items` + `order_adjustments` + `payments`; `carts` + `cart_items` |
| `categories_items` | не переносится, индекс строится заново |

Столбец `language` и служебные JSON (`plugins`, `changelogs`, `logs`) не переносятся. Переносим только данные, код RM не используем.

## 9. Критерии производительности

Генератор данных (в `dev/bench/`) создаёт 100 / 10 000 / 100 000 товаров, ~20 полей, 3 уровня категорий, 1–6 вариантов. Замеры по этим трём размерам; рост между 100 и 100 000 должен быть минимальным.

| Операция | Цель (время БД, локально) |
|---|---|
| Список категории (сортировки: порядок, цена, новизна, название), 24 позиции | ≤ 30 мс |
| То же + диапазон цены + 3 фильтра по полям | ≤ 80 мс |
| Карточка товара (товар + варианты + поля) | ≤ 20 мс |
| Поиск по названию / артикулу | ≤ 100 мс |
| Сохранение товара с 20 полями и 5 вариантами (с индексом) | ≤ 300 мс |
| `EXPLAIN` основных запросов | без `Using filesort` и полного сканирования на путях листинга |

Цели — стартовые гипотезы. Результаты первых замеров (2026-10-06): все цели по чтению выполнены с запасом, сохранение товара ещё не измерено — см. `BENCHMARKS.md` и `BENCHMARKS_NOTES.md`. Для сравнения замеряется и RadicalMart на отдельной копии базы dharmatest (10 и 50 тыс. товаров); на самом dharmatest массовые данные не оставляем.

## 10. Этапы (утверждено 2026-10-06)

После каждого этапа — показ пользователю и приёмка.

- **Этап 0: схема и скорость, без интерфейса.** Репозиторий, каркас пакета, установочные SQL, установка на test.test, генератор данных, замеры на трёх размерах. Результат: подтверждённая скорость схемы до написания админки.
- **Этап 1: товары и категории.** Админка товара с одним вариантом, штатные категории, список, карточка, плитка на сайте, витринный индекс.
- **Этап 2: поля и фильтр** (включая галерею, файлы, списки, числа).
- **Этап 3: несколько вариантов у товара** (оси, матрица вариантов).
- **Этап 4: поиск, порядок товаров, перенос с RadicalMart.**
- **Этап 5: корзина, заказы, покупатели, статусы, письма, «заявка».**
- Далее — плагины доставки и оплаты, наши экспорт/импорт, аналитика; по запросу — склады, мультивалюта, 1С.

## 11. Риски и неизвестное

1. **Фильтры по полям на 100 тыс. товаров.** Замеры этапа 0 (`BENCHMARKS.md`): худший случай — корень каталога + цена + 3 фильтра — 32 мс на 100 тыс., рост линейный по числу кандидатов (на 1 млн ожидаем ~300 мс). Запасной приём — сначала выбрать кандидатов по самому селективному полю, затем соединить с индексом. Нужен ещё прогон с неудобными значениями (`BENCHMARKS_NOTES.md`).
2. **Витринный индекс — денормализация.** Согласованность при перемещении категорий и массовых правках; страховка — `reindex` и тест на консистентность.
3. **Счётчики фильтра** на лету слишком дороги (50–100 мс на поле на 100 тыс. товаров, замер этапа 0): показываются по настройке из кэша или из предрасчёта (предложение E13).
4. **Формат `products.fields` RadicalMart** не разобран; перенос полей уточнится на этапе 4.
5. **Минимум MySQL/MariaDB** — наш выбор; на стенде MySQL 8.4.3.
6. **Модуль товаров на крупных каталогах.** Сравнение с RadicalMart (`BENCHMARKS_RM_NOTES.md`): запрос «выбрать 12 товаров» без индекса просматривает всю таблицу товаров и выполняется на любой странице сайта (133 мс на 50 тыс.). `mod_dharmashop_products` ограничивает выдачу и использует условия фильтра по индексам, однако отдельные замеры списка «статья + несколько модулей товаров», включая поля и группы, ещё нужны.

### Уточнение перед выводом категорий (2026-10-07)

По C27 оформление витрины выполняется классами Bootstrap 5; отдельный CSS компонента и собственные inline-стили не добавляются. Страница списка категорий и карточка категории — разные шаблоны. Разделение SEO принято в C28/C29: `Site\Seo\PageSeo` готовит базовые метаданные, `plg_dharmashop_social` добавляет социальные теги, `plg_dharmashop_structureddata` — Schema.org через `onDharmaShopMetadataReady`. Обновление 2026-10-08: подключены к спискам разделов/товаров и товару; подробнее в `docs/SEO.md`.

Schema.org списка категорий: вид передаёт семантические данные уже отобранных карточек в `CollectionMetadata`, отдельно от SEO-строк. При отключённом `seo_structureddata` контекст не готовится. Плагин создаёт один актив WebAssetManager `inline.dharmashop.structureddata` (`application/ld+json`): граф из `CollectionPage` (canonical + `#webpage`) и `ItemList` (canonical + `#itemlist`), элементы — `ListItem` с позициями от 1 и абсолютными маршрутами карточек. Пустая ветка — пустой список. JSON кодируется с HEX-флагами и заменой некорректного UTF-8, без ручной склейки HTML. `BreadcrumbList` остаётся за штатным модулем Joomla; свои хлебные крошки и Organization пока не генерируются. Product/Offer добавлены 2026-10-08 для страницы товара через ProductMetadata. Плагин включается при первом добавлении пакетом, при обновлениях прежнее отключение сохраняется.

### Настройки сеток и карточек C30 (2026-10-07)

Вкладка отображения содержит пять вложенных нативных fieldset: категории, товары категории, бренды, бейджи, страница товара. Параметры остаются плоскими. Для списков `Site\Support\Grid` выбирает простой адаптивный режим либо все шесть брейкпоинтов Bootstrap, по 1–6 колонок. Для карточек разделов — интро/количество/цены; для товаров категории — интро карточки и общие итоги списка. На странице отдельного товара выбирается макет.

Рабочий вид категорий наследует отображение от магазина, родителей и записи, затем применяет непустые поля меню. Пустое значение наследует, 0 отключает. Страница получает `gridClasses`; карточка — флаги и готовые значения без SQL. Статистика только по показанным ID: один GROUP BY по индексу `catid`/`state`, цены через PK товара и готовые `price_min`/`price_max`. При выключенных количестве и ценах запроса нет. Скорость нового запроса на большом каталоге не измерена. Остальные виды не реализованы, их настройки и слоты `brand-card`/`badge-card` — заготовки. Реестр ключей и поведение — `docs/LAYOUTS.md`.

SEO, 2026-10-08: ProductMetadata передаёт Product/Offer из доступного товара; Offer без форматирования и только при известной единственной цене (не «по запросу»). CollectionMetadata для списков товаров имеет itemType=Product. PageMetadata дополнен необязательным imageAlt; ImageUrl использует контекст seo.social (1200×630), prepareImage=false при вычислении только крошек. Приоритет изображения: meta_image → cover/image → общая SEO-картинка. Social/Structureddata не запрашивают каталог. Подробно — SEO.md.

Offer.availability, 2026-10-08: ProductModel существующим запросом выбранного опубликованного SKU читает stock_tracked/stock_total и готовит offer_in_stock. Без учёта → true, учтённый остаток >0 → true, <=0/нет активного варианта → false. ProductMetadata.inStock получает это значение вместо агрегированного products.in_stock, который учитывает backorder. Условия корзины и складской индекс сохраняются.

Вкладки товара, 2026-10-08: EntityFields добавляет group_id/group_title в готовые определения через существующий JOIN групп; Product HtmlView группирует только видимые непустые значения в fieldGroups. default_tabs.php выводит fulltext и группы штатным UITab с breakpoint=768. Никаких запросов в шаблонах, новых запросов или изменения схемы. Контракт/переопределение — LAYOUTS.md.

Интро товара, 2026-10-08: DisplaySettings нормализует params.show_intro (по умолчанию 0), EntityFields передаёт отдельный show_intro для product/page. default_intro.php выводит только отмеченные заполненные поля через fields/default с context=intro; in_page по-прежнему управляет вкладками. Никакой миграции схемы/массового сохранения, новых SQL или ресурсов. LAYOUTS.md / FIELDS.md.

### Связанные товары, 2026-10-09

Повторяемый `modal_product` с `unique="true"` использует штатный Joomla ModalSelectField и небольшое разрешённое дополнение `com_dharmashop.product-picker`. Перед открытием диалога оно передаёт IDs выбранных в ближайшем subform товаров и ID редактируемого товара в `picker_exclude`. ProductsModel учитывает этот ограниченный список (до 101 ID) только для layout=modal через привязанный whereNotIn; исключения не сохраняются в сессии основного списка. Нативные поиск, сортировка и пагинация сохраняют параметр. Другие блоки остаются независимыми; при очистке/удалении строки следующий диалог снова предлагает товар. Сохранение ручных связей дополнительно удаляет дубли на сервере, как прежде.

Блок поддерживает grid/list/slider. Слайдер использует grid-карточки и существующий контекст изображений `product.related.grid`, общий Swiper через WAM preset `dharmashop.swiper` и независимый инициализатор плагина с зависимостью от него. Шесть адаптивных значений числа карточек и настройки управления/движения хранятся в строке `related_blocks`; таблиц и запросов слайдер не добавляет. При недоступной библиотеке — сетка. Обёртка слайдера отдельно переопределяется в шаблоне.

Системный плагин `dharmashoprelated` подключает собственную вкладку com_config и поля ручного выбора через нативный `onContentPrepareForm`, нормализует блоки через `onExtensionBeforeSave`, ручные связи — через `onContentBeforeSave`. Отображение использует существующие события товара из §6. Настройки `related_enabled` (по умолчанию 0), `related_blocks`; стабильный alias блока, связи в уже загруженном `products.params.related_<alias>`. Прямые ручные назначения хранятся в params; для взаимных связей добавлен производный индекс плагина dharmashop_related_products (source_id, block_alias, target_id), поддерживаемый стандартными onContentAfterSave/onContentAfterDelete. JSON-запросов нет. Авто по категориям использует индекс product_categories; по полям — индексируемые равенства field_values, переключаемое условие fields_operator (and по умолчанию / or), совпадения одного опубликованного варианта кандидата. Для OR начальные наборы кандидатов всех полей объединяются UNION, для AND используется первое поле. Данные карточек собираются пакетно для объединения всех блоков; общая поддержка `Site\Support\ProductCardRenderer`. Подробно: RELATED_PRODUCTS.md.


## 11. Группы самостоятельных товаров (C87, 2026-10-09)

Актуальный контракт реализации — `VARIANTS.md` §14; первоначальные схемы внутренних вариантов выше остаются историей и резервом хранения. Используются `product_groups`, `group_axes`, `group_media`, nullable `products.group_id/combo_hash` и режимы `inherit_intro/full/images` (`own/group/empty`). Уникальность — `(group_id,combo_hash)`. Реальные значения по-прежнему в `field_values`, уровень товара, `variant_id IS NULL`; хеш SHA-256 от сортированных по ID поля пар `field_id:option_id` с префиксом `v1|`.

`GroupService`: проверка полей/сочетаний, пакетное чтение значений при объединении, блокировки групп/товаров и уникальный индекс, пересчёт производных данных, наследование и материализация при исключении. Записывающий вызывающий код владеет транзакцией (как `FieldValues::save`); `ProductModel` начинает её после Joomla asset-записи. Форма группы имеет оптимистическую `revision`, чтобы старая форма не стирала изменённый состав. Таблица группы без asset_id, её собственные данные и связи сохраняются одной транзакцией.

Свой источник изображений — основная галерея товара. `products.cover/cover_alt` содержат эффективную обложку: обновляются при сохранении товара или галереи группы; существующие карточки, корзина и письма получают её через прежние JOIN. Своя галерея сохраняется при наследовании; исключение материализует эффективную галерею. Тексты разрешаются в карточке и поисковом индексе, собственные тексты остаются в products; списку с интро понадобится пакетное разрешение/SQL CASE в последующей работе Claude.

`product.choose` — GET без мутаций: точное сочетание → product_id через уникальный индекс → обычная проверка публикации/доступа категории. Недоступное сочетание возвращает к исходному товару с предупреждением. Нет автоматической подмены другого признака, новых JS/CSS или публичного маршрута группы. Шаблон `product/default_choices.php` переопределяемый.

Нативная генерация строк исполнений — до 100 за запуск, сначала полная предварительная проверка, затем отдельные ProductModel::save без внешней транзакции. Успешные строки сохраняются, неуспешные остаются в форме. Матрица, формулы, предпросмотр, наследование SEO/таксономии и MCP пока идеи §13.1, не реализованные возможности.


Уточнение C87, 2026-10-09: единицы выдачи определяет общая настройка category_listing_mode=products|groups_products (по умолчанию products), прежний display_mode группы игнорируется. Общий product_view=grid|list|table действует на смесь карточек товаров и групп. ListingTrait передаёт отмеченного представителя (listing_type=group, listing_group) в GroupCardRenderer; свои JLayout группы и их части не выполняют SQL. Контракт данных и подключение фильтра/total — VARIANTS.md §14.6.

C87, форма/таксономия группы (2026-10-09): вкладки «Товары» (axes/members) и «Содержимое» (gallery/introtext/fulltext), сайдбар как у товара. product_groups.catid/brand_id — nullable и индексируемые; group_categories(group_id,catid,is_main) — прямые связи категорий, производителя и бейджей, без родительских строк/товарного индекса. GroupTaxonomy::prepare/get/store и GroupService::taxonomy; запись в транзакции ProductgroupModel с revision, очистка при удалении. Старые группы не перепривязываются, основная категория обязательна при сохранении. Генератор копирует сохранённую таксономию в новые товары через ProductModel; существующие участники не меняются. Подробности/граница фильтра — VARIANTS.md §14.7.

Общий modal_product (2026-10-09): Catalog\ProductPicker::preload пакетно читает выбранные product_id из корневого Form::getData в ProductModel/ProductgroupModel до создания строк subform (до 500 за запрос, cache на запрос/DB-соединение; отдельные row Forms видят только свою строку), одним JOIN получает единственный SKU и его обычную/итоговую цену/скидку. Нативный список модального окна использует уже готовые строки ProductsModel, без новых запросов на товар. Представление цены через MoneyFormatter/Discount, расчёта новой цены нет. Дополнительные plain-данные проходят штатный postMessage диалога, обновление Bootstrap-превью в product-picker.js по native change. Нет своего AJAX, диалога и CSS. Контексты/реестр — LAYOUTS.md и IMAGE_RESIZE.md.

Группа в карточке товара (2026-10-09): modal_productgroup/field.group-picker в боковом блоке категорий, вместо отдельной вкладки. Диалог productgroups/modal читает страницу доступных групп с готовыми cover/price_min/max/members_count; отдельный контекст состояния списка, штатные searchtools/ModalSelect/postMessage. product.createGroup вызывает обычный FormController::save; postSaveHook только после успеха открывает новую несохранённую группу с товаром/названием/таксономией. Запись группы и назначение участника остаются за ProductgroupModel/GroupService. Схема и фильтр витрины не менялись; VARIANTS.md §14.8.

Группы, уточнение 2026-10-09: catid=1 может означать системный ROOT Joomla только у группы (проверяется system/parent_id=0/level=0); прямая основная связь сохраняется в group_categories. Обычная новая форма без выбранной категории, объединение получает catid первого выбранного товара; генератор требует обычную категорию. VARIANTS.md §14.9. Запись групп использует переменные объектов для by-reference API insertObject/updateObject Joomla.

Смешанная выдача включена 2026-10-09 по прямому указанию пользователя. GroupListing применяет собственную таксономию/ACL группы, выбирает представителя под сортировкой до пагинации и пакетно готовит показатели подходящих участников. CategoryModel передаёт forListing=true списку/его total/итогам; обычные запросы фасетов остаются прежними. Ключ единицы: product_id или отрицательный signed group_id, без JSON/новых столбцов. Контракт и ограничения счётчиков фильтра — VARIANTS.md §14.10. Нагрузочные замеры впереди.


Карточки групп (2026-10-10): group_card_mode=simple|ajax; глобальный состав выдачи остаётся отдельным. GroupListing/CategoryModel готовят выбранных представителей, их значения/цены/галереи пакетно; настройки осей лежат в fields.params, значения по-прежнему индексные. GroupCardRenderer повторно использует ProductCardRenderer и готовую карту корзины страницы, без запроса на каждую карточку. GroupCardContext подписывает публичный контекст выдачи; groupcard.select читает один вариант через GroupService::resolve и повторную проверку того же candidate query, не меняя данные. HTML одной карточки возвращается no-store/noindex, CSS/матрицы вариантов нет; group-card.js обновляет корень, отменяет старые запросы, блокирует покупку до ответа и переинициализирует прежние скрипты событием dharmashop:updated. Прежние именованные контексты изображений и переопределения JLayout сохраняются. Контракт/ограничения и открытая приёмка — VARIANTS.md §14.11, TASK_GROUP_CARDS.md.

Генератор групп (2026-10-11): `Catalog/GroupGenerator` готовит ограниченный черновик сочетаний без записи и проверяет всю партию до `ProductModel::save`. `ProductgroupModel` сохраняет группу и последовательно создаёт самостоятельные товары, сохраняя оставшиеся строки при сбое. Нет внешней транзакции вокруг товарных asset writes. Формула `product_groups.title_template` (varchar 1000) — постоянная административная настройка; строки/пулы значений хранятся только в сессии редактора по ID группы. GroupStorage добавляет колонку аддитивно; обычный GroupService/выдача формулу не выбирают. Native subform/JLayout и административный group-generator.js не создают frontend зависимости. VARIANTS.md §14.13.

Общие поля группы (§14.14): `product_groups.copy_fields` и три типизированные `group_field_values/texts/media`, владелец group_id, определения target=product. FieldValues переиспользует провайдеры, исключает оси группы и проверяет категории. GroupGenerator читает общие значения один раз, валидирует всю партию до записи и передаёт снимок обычному ProductModel. Сохранение группы не меняет поля существующих товаров. На витрине common storage не читается. EntityFields использует индекс владельца для ограниченного чтения текущих карточек: устранён обнаруженный обход по NULL variant_id. Проверки/замеры — QA_GROUP_COMMON_FIELDS.md.

## Аудит настроек отображения (2026-10-12)

`Site\Support\DisplaySettings` применяет непустые значения параметров по уровням; `Category\ListingTrait` отдельно сохраняет нулевой маркер `list_limit`. До чтения списка `CategoryModel::setListingMode()` получает итог из общих настроек/родителей/записи/меню. Страница товара разрешает общие настройки → параметры товара → собственное меню для макета, галереи, скидки и таймера. Избранное использует `Support\Grid` для общей и меню-сетки XS–XXL. Матрица параметров и полные правила наследования — `DISPLAY_SETTINGS.md`.
