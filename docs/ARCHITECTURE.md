# Dharma Shop — архитектура

Статус: утверждена пользователем 2026-10-06 в части модели «товар → варианты», поэтапной разработки и названий (см. `DECISIONS.md`). Схема БД реализуется поэтапно (раздел 10). Если реализация расходится с документом, правится документ.

Для тех, кто продолжает работу (человек или ИИ): сначала `START_HERE.md` (исходное задание), затем `DECISIONS.md`, затем этот файл.

## 1. Принципы

1. **Нативно Joomla 6** (PHP ≥ 8.3, MySQL 8+/MariaDB 10.6+): пространства имён, сервис-провайдеры, `SubscriberInterface`, Web Asset Manager, `RouterView`, права через `access.xml`, консольные плагины, планировщик задач, шаблоны писем.
2. **Админка без своего JS/CSS:** формы только Form XML (`subform`, `showon`, `uitab`, `searchtools`), штатный тулбар и сообщения. Исключений нет.
3. **Масштаб с первого дня:** любой запрос витрины идёт по индексу, без `JSON_*`, без `LIKE '%…%'`, без больших `OFFSET`. Критерии — раздел 9.
4. **Ядро маленькое:** то, что нужно не каждому магазину, — плагин. Ядро даёт события и бандлит минимум, чтобы магазин работал «из коробки».
5. **Чистая комната:** своя модель, свои таблицы, свои имена. Код RadicalMart не читаем и не переписываем; от него нужны только схема таблиц (для переноса данных) и поведение в админке как справка.
6. **Одноязычный магазин** (C2): в схеме нет столбца `language`; переводится интерфейс.
7. **Деньги — `DECIMAL`, в PHP строками** (без `float`); все суммы в одной валюте строки прайс-листа.

## 2. Модель предметной области

```
Категория (штатная Joomla)
   └── Товар ───────── карточка: название, описание, SEO, категории, поля товара
          └── Вариант ─ то, что продаётся: артикул, цены, остаток, поля варианта
                           (у любого товара есть минимум один вариант)
Поле ─ типизированный блок данных (строка, число, список, галерея, файл…), у товара или у варианта
Корзина → Заказ (позиции = варианты, строки итогов, статусы с переходами, оплаты)
```

- **Типы категорий (2026-10-06, решение пользователя).** «Категория» бывает трёх типов: **категория** (дерево, адрес товара, основная и дополнительные), **бренд** (у товара один) и **бейдж** (плашка на карточке — «Хит», «Новинка», «Акция»; у товара несколько). Технически это три **секции** штатных категорий Joomla: `com_dharmashop`, `com_dharmashop.brand`, `com_dharmashop.badge` — у каждой свой раздел в менеджере категорий и свои дополнительные поля формы.
- **Простой товар** — товар с одним вариантом. В форме товара его артикул, цена и остаток показаны сразу, вкладки «Варианты» нет (`variants_mode = 1`).
- **Товар с вариантами** (`variants_mode = 2`) — выбираются «оси» (поля уровня «вариант», например «Цвет» и «Размер»), варианты создаются по значениям осей. Своего раздела «Мета-товары» нет: варианты правятся во вкладке товара.
- В корзину и заказ всегда попадает **вариант**, особых случаев нет.
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
| Модули | `mod_dharmashop_categories`, `_search`, `_filter`, `_cart`, `_products` |
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
  media/                          joomla.asset.json, css на переменных --dharma-*, минимум js
plg_system_dharmashop/            слияние корзины при входе, хуки жизненного цикла
plg_task_dharmashop/              задачи планировщика: старт/конец скидок, очистка корзин, переиндексация
plg_dharmashopshipping_standard/  фиксированная цена, бесплатно от суммы, самовывоз
plg_dharmashoppayment_offline/    «при получении / по счёту» (без онлайн-платежа)
mod_dharmashop_*/                 модули
```

Отдельно от базового пакета: `plg_console_dharmashopmigrate` (перенос с RadicalMart), `plg_dharmashop_request` (заявка), `plg_dharmashopnotify_telegram`, `plg_finder_dharmashop`, типы полей, службы доставки и оплаты, экспорт/импорт, аналитика.

Уроки из прошлых проектов: пакет только из вложенных zip; пустые папки в манифесте ломают установку; Apache (Kettle) перезапускать после нового виртуального хоста; в CLI Joomla подставляет `live_site` в маршруты (`/set/by/console/application`), поэтому консольным командам адрес сайта нужен из настроек.

## 5. Схема БД

Общее: InnoDB, `utf8mb4_unicode_ci`, даты `DATETIME NULL`, `checked_out`/`checked_out_time` там, где есть редактирование в админке. JSON-столбцы — только для редких, никогда не фильтруемых данных (`params`).

### 5.1 Категории, бренды, бейджи

Штатные `#__categories` (дерево, права, состояние, метаданные, изображение) в трёх секциях через столбец `extension`. **Три раздела меню, типы не смешиваются** (C8):

| Раздел меню / секция (`extension`) | Что это | Связь с товаром | Вложенность | Поля сверх штатных (`params`) |
|---|---|---|---|---|
| Категории / `com_dharmashop` | категория | основная (`products.catid`, адрес товара) + дополнительные | да | `show_in_list`, `list_limit`, `list_ordering`, изображение |
| Бренды / `com_dharmashop.brand` | бренд | один на товар | **да** (производитель → линейка) | то же + `brand_website` |
| Бейджи / `com_dharmashop.badge` | бейдж | несколько на товар | нет | `badge_color` (класс Bootstrap), `badge_show_list`, изображение |

**Экран — наш, не `com_categories`** (E17): `CategoriesModel`/`CategoryModel` + `CategoriesController`/`CategoryController` + виды `categories` (список одного типа, параметр `extension`) и `category` (форма). Список: корневая запись (служебная, только у категорий и брендов), перетаскивание порядка (`saveOrderAjax`), состояние, изображение, отображение, **счётчик товаров по индексу** (опубликованных и всего, включая подкатегории), доступ, ID; тулбар: создать, действия, «Перестроить» (`Table::rebuild`), настройки. Форма: основное (изображение, иконка, вводный текст и полное описание в двух редакторах; в боковой колонке тип, родитель, состояние, доступ, заметка), отображение, поля типа, метаданные, права. Изображение, иконка и вводный текст хранятся в `params` категории, полное описание — в штатном `description`; миниатюра в списке: изображение, иначе иконка. Таблица `CategoryTable` наследует штатную `Joomla\CMS\Table\Category`; единственное отличие — корневые записи типов наследуют права компонента (`_getAssetParentId`). Имена ресурсов прав — штатные: `<extension>.category.<id>`.

Правила: тип задаётся при создании (параметр `extension`) и не меняется; родитель — корень или запись того же типа; удалять можно только пустые записи (без вложенных и товаров), потому что штатное удаление дерева сносит потомков; перемещение записи в дереве перестраивает родительские строки индекса у товаров поддерева (`Indexer::rebuildCategorySubtree`).

Все три типа хранят привязку товара **в одной таблице витрины** `product_categories` (раздел 5.4): идентификаторы категорий в Joomla общие на все секции, поэтому листинг бренда или бейджа — тот же индексный проход, что и листинг категории; фильтр «категория + бренд» — проверка по первичному ключу `(catid, product_id)`.

Дополнительно: `#__dharmashop_category_fields (catid, field_id)` — какие поля показывать в фильтре и карточке категории (для полей с `all_categories = 0`).

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
  `state`         tinyint NOT NULL DEFAULT 0,           -- 1 опубликован, 0 снят, 2 архив, -2 корзина
  `featured`      tinyint unsigned NOT NULL DEFAULT 0,
  `variants_mode` tinyint unsigned NOT NULL DEFAULT 1,  -- 1 один вариант, 2 несколько
  `unit`          varchar(20) NULL,                     -- правила оформления заказа: NULL = наследовать (C14, §5.16)
  `qty_min`       decimal(12,3) NULL,
  `qty_step`      decimal(12,3) NULL,
  `qty_max`       decimal(12,3) NULL,                   -- 0 = без ограничения
  `stock_tracked` tinyint unsigned NULL,                -- NULL = наследовать; итог копируется в варианты
  `backorder`     tinyint unsigned NULL,
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
  `length`      decimal(10,2) NULL,
  `width`       decimal(10,2) NULL,
  `height`      decimal(10,2) NULL,
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
| `#__dharmashop_variant_prices` | `variant_id`, `price_list_id`, `price` decimal(15,2) NULL (NULL = «по запросу»), `sale_price` NULL, `sale_start` NULL, `sale_end` NULL, `price_final` decimal(15,2) NULL (эффективная; пересчитывается при сохранении и задачей планировщика). PK `(variant_id, price_list_id)`, `KEY (sale_start)`, `KEY (sale_end)` |
| `#__dharmashop_stock_locations` | `id`, `title`, `is_default`, `state`, `ordering`. При установке — один склад `id = 1`. Склады в интерфейсе — этап 4 |
| `#__dharmashop_stock_levels` | `variant_id`, `location_id`, `qty` decimal(12,3), `reserved` decimal(12,3). PK `(variant_id, location_id)` |
| `#__dharmashop_stock_movements` | журнал: `id` bigint, `variant_id`, `location_id`, `delta`, `reason` (order/manual/import/return/…), `order_id` NULL, `user_id` NULL, `note`, `created`. `KEY (variant_id, created)`, `KEY (order_id)` |

Остаток меняется только через `StockService` (пишет движение, пересчитывает `stock_total`/`in_stock`, вызывает событие).

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
  `featured`    tinyint unsigned NOT NULL DEFAULT 0,
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

Запись: `Indexer::setCategories(товар, основная, дополнительные, бренд, бейджи)` задаёт прямые привязки (`direct = 1`, ручной `sort_order` у сохраняемых не теряется), затем `rebuildRange()` пересобирает родительские строки и копии столбцов одним набором запросов (то же применяется к диапазону id при полной пересборке `rebuildAll()` и будущей команде `reindex`).

Страница списка: 1) выбрать `product_id` страницы по индексу; 2) загрузить карточки `WHERE id IN (...)` по первичному ключу. Постраничность штатная для небольших смещений, на больших — курсор по `(ключ сортировки, product_id)`; порог — в настройках. Общее число — `COUNT` с верхним пределом, для категорий без фильтра — кэш.

### 5.5 Поля, группы и значения

| Таблица | Столбцы |
|---|---|
| `#__dharmashop_fieldgroups` | `id`, `title`, `alias`, `ordering`, `state`, `checked_out*` |
| `#__dharmashop_fields` | `id`, `group_id`, `alias` (`UNIQUE`), `title`, `type` (id типа в реестре), `store` (`option`/`number`/`string`/`text`/`media`), `level` (1 товар, 2 вариант), `all_categories`, `filterable`, `sortable`, `in_card` (плитка), `in_page` (страница), `is_cover` (обложка: копия первого файла в `products.cover`), `required`, `unit`, `ordering`, `state`, `access`, `params` (настройки типа), `checked_out*` |
| `#__dharmashop_field_options` | `id`, `field_id`, `alias`, `title`, `color`, `image` (необязательные), `ordering`; `UNIQUE (field_id, alias)` |
| `#__dharmashop_product_axes` | `product_id`, `field_id`, `ordering` — по каким полям уровня «вариант» построены варианты товара; PK `(product_id, field_id)` |

Значения:

```sql
CREATE TABLE `#__dharmashop_field_values` (
  `id`         bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int unsigned NOT NULL,        -- всегда заполнен (и у полей варианта: товар-владелец)
  `variant_id` int unsigned NULL,            -- NULL: поле товара; иначе поле варианта
  `field_id`   int unsigned NOT NULL,
  `option_id`  int unsigned NULL,            -- store=option
  `num`        decimal(18,6) NULL,           -- store=number
  `str`        varchar(190) NULL,            -- store=string
  PRIMARY KEY (`id`),
  KEY `idx_product`  (`product_id`, `field_id`),
  KEY `idx_variant`  (`variant_id`, `field_id`),
  KEY `idx_option`   (`field_id`, `option_id`, `product_id`),
  KEY `idx_num`      (`field_id`, `num`, `product_id`),
  KEY `idx_str`      (`field_id`, `str`, `product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- `#__dharmashop_field_texts` (`id`, `product_id`, `variant_id` NULL, `field_id`, `value` mediumtext; `KEY (product_id, field_id)`) — длинный текст и HTML, в фильтре не участвует.
- `#__dharmashop_field_media` (`id`, `product_id`, `variant_id` NULL, `field_id`, `path` varchar(500), `alt` varchar(255), `title` varchar(255), `ordering`; `KEY (product_id, field_id, ordering)`) — **галерея, файлы, изображения**. Форма — штатное поле Media в `subform`, при сохранении строки пишутся в таблицу.
- Мультивыбор — несколько строк `option_id` на пару «товар/вариант — поле».
- Фильтр по полю — `EXISTS (SELECT 1 FROM field_values WHERE product_id = pc.product_id AND field_id = ? AND option_id IN (...))`; число — по диапазону `num`. Поле варианта находит товар, если подходит **любой** его вариант; строгое совпадение «этот цвет и этот размер в одном варианте» возможно (есть `variant_id`), но в v1 не включено.
- Счётчики значений фильтра («Красный (12)») дорогие: считаются по требованию, кэшируются, включаются настройкой.
- Типы полей расширяются плагинами группы `dharmashopfield`: тип сообщает `store`, XML редактирования, логику фильтра и вывода.

### 5.6 Поиск

`#__dharmashop_search`: `product_id` PK, `title_norm` varchar(255), `body` mediumtext (описание без HTML + значения строковых полей); `FULLTEXT (title_norm, body)`. Поиск по артикулу — префиксный по `variants.sku` (`uq_sku`). Интеграция с Умным поиском Joomla — плагином `finder`.

### 5.7 Корзины

```
#__dharmashop_carts       id, token char(32) UNIQUE (в cookie), user_id NULL, customer_id NULL,
                          price_list_id, created, modified, expires; KEY (user_id), KEY (expires)
#__dharmashop_cart_items  id, cart_id, variant_id, qty decimal(12,3), line_hash char(32),
                          params text (JSON: срок аренды и т. п.), UNIQUE (cart_id, line_hash), KEY (variant_id)
```

Цена в корзине не хранится: пересчитывается при каждом чтении (`PriceResolver`), в заказ уходит снимок. Итоги собираются событием; плагины добавляют строки «скидка», «налог», «сбор», «доставка».

### 5.8 Покупатели

`#__dharmashop_customers`: `id`, `user_id` NULL (`UNIQUE`), `name`, `email` (`KEY`), `phone`, `phone_norm` (`KEY`), `note`, `params`, `created`, `modified`. Создаётся при первом заказе, связывается с пользователем Joomla по `user_id` или email при входе (`plg_system_dharmashop`).

### 5.9 Заказы

| Таблица | Столбцы |
|---|---|
| `#__dharmashop_orders` | `id`, `number` (`UNIQUE`), `secret` char(32), `kind` (1 заказ, 2 заявка), `customer_id`, `user_id`, `status_id` (`KEY`), `currency`, `subtotal`, `adjustments_total`, `total` decimal(15,2), `contact_name`, `contact_email`, `contact_phone` (`KEY`), `contacts` (JSON: прочие поля оформления), `note`, `shipping_method_id`, `shipping_title`, `shipping_data`, `payment_method_id`, `payment_title`, `payment_data`, `cart_id`, `created` (`KEY`), `created_by`, `modified`, `modified_by`, `checked_out*` |
| `#__dharmashop_order_items` | `id`, `order_id` (`KEY`), `variant_id` NULL, `product_id` NULL, `sku`, `title`, `unit`, `qty`, `price`, `total`, `params`, `ordering` — снимок на момент заказа |
| `#__dharmashop_order_adjustments` | `id`, `order_id`, `type` (shipping/discount/tax/fee), `code`, `title`, `amount` (может быть < 0), `params`, `ordering` |
| `#__dharmashop_order_history` | `id`, `order_id`, `status_id`, `user_id` NULL, `comment`, `notified`, `created`; `KEY (order_id, created)` |
| `#__dharmashop_payments` | `id`, `order_id`, `method_id`, `amount`, `currency`, `state` (pending/paid/failed/refunded), `external_id` (`UNIQUE` вместе с `method_id`: защита от повторных callback), `data`, `created`, `modified` |
| `#__dharmashop_statuses` | `id`, `title`, `alias` (`UNIQUE`), `color` (класс Bootstrap), `is_default`, `is_final`, `stock_effect` (0 нет / 1 резерв / 2 списание / 3 снять резерв), `notify_customer`, `notify_admin`, `customer_message`, `ordering`, `state` |
| `#__dharmashop_status_transitions` | `from_status_id`, `to_status_id`; PK обоих. Если у статуса переходов нет — разрешены любые |

Номер заказа — формат из настроек. Статус меняется только через `OrderStatusService` (проверка перехода, история, движение остатков, письма, событие). Заявка — заказ с `kind = 2`: позиция может быть без цены, оплаты нет.

### 5.10 Способы доставки и оплаты

```
#__dharmashop_shipping_methods  id, title, alias UNIQUE, plugin (element плагина группы dharmashopshipping),
                                description, params (JSON настроек плагина), is_default, state, access, ordering, checked_out*
#__dharmashop_payment_methods   то же; plugin — группы dharmashoppayment
```

Один плагин может обслуживать несколько записей.

### 5.11 Служебное

`#__dharmashop_external_ids`: `entity`, `source`, `external_id`, `id`; `UNIQUE (entity, source, external_id)` — соответствие внешних идентификаторов (перенос, 1С, YML).

### 5.12 Чего в схеме нет

Языковых версий (C2), доступа по группам к товару и дат публикации (E6), мультивалютных цен и складов в интерфейсе (этап 4, схема готова), истории изменений товара (штатная `com_contenthistory` — позже), просмотров (аналитика — отдельный компонент).

### 5.13 Сохранение товара (реализовано на этапе 1)

`ProductModel::save()` (форма и CLI), порядок важен:

1. **Проверки, которые могут не пройти, — до записи:** уникальность артикула среди вариантов других товаров (`VariantRepository::assertSkuAvailable`). Алиас уникален в основной категории (проверка в `ProductTable::store`).
2. **Карточка:** `AdminModel::save()` (таблица `products`, права/asset, события Joomla). **Её нельзя оборачивать в транзакцию:** `Table::store()` для ресурса прав берёт `LOCK TABLES`, а это неявно фиксирует транзакцию (проверено на практике: откат ничего не убирал).
3. **Зависимые данные — в транзакции:** вариант по умолчанию и его цена (`price_final` по `PriceResolver`), остаток основного склада и запись движения (`StockService`), производные поля товара (`ProductRebuilder`), привязки и индекс витрины (`Indexer`). Если шаг 3 упал, новая карточка удаляется (компенсация), ошибка пишется в журнал `administrator/logs/com_dharmashop.log.php` и в сообщение формы.

Удаление (`delete`) после штатного удаления карточки чистит варианты, цены, остатки, индекс, поиск, оси; публикация/состояние обновляют копии в индексе (`refreshProducts`).

### 5.14 Команды обслуживания (реализовано на этапе 1)

Производные данные (индекс витрины, цена «от/до» и наличие товара, эффективная цена с акциями) можно пересчитать. Меню «Команды» на списке категорий (аналог RadicalMart), права `core.manage`:

| Команда | Что делает | Служба |
|---|---|---|
| Пересобрать витринный индекс товаров | родительские строки и копии столбцов сортировки для всех товаров; убирает строки несуществующих товаров | `Indexer::rebuildRange` |
| Пересчитать цену и наличие товаров | `price_min`, `price_max`, `in_stock`, `variants_count` из вариантов, затем копии в индексе | `ProductRebuilder::rebuildRange` |
| Пересчитать акционные цены по датам | у вариантов, у которых акция началась или закончилась, пересчитывает `price_final`, цену товара и индекс (один набор запросов) | `SaleScheduler::run` |

Дерево категорий пересчитывает отдельная кнопка «Перестроить». Все команды — `Maintenance\MaintenanceService`: проход по товарам порциями по ключу (`id > курсор`), бюджет времени на один запуск (8 с); если каталог не закончился, курсор хранится в сессии пользователя, и повторный запуск продолжает. Эти же службы будут использовать консольная команда `dharmashop:reindex` (полный прогон больших каталогов без ограничений веб-запроса) и задача планировщика Joomla (`SaleScheduler`).

### 5.15 Загрузчик изображений (реализовано на этапе 1, C13)

Поле формы `imageupload` (`Field\ImageuploadField`, атрибут `folder` — подпапка): область для перетаскивания файла или выбора по клику, предпросмотр, «Убрать», прогресс. Скрипт — `media/com_dharmashop/js/uploader.js` (стили `css/uploader.css`), активы описаны в `media/com_dharmashop/joomla.asset.json`, подключаются через Web Asset Manager.

Загрузка: `POST index.php?option=com_dharmashop&task=media.upload&format=json` (`MediaController::upload`) с токеном формы; права — `core.create` или `core.edit` компонента. `Image\ImageUploader` проверяет: код загрузки и размер (меньший из настроек «Медиа» Joomla и PHP), расширение из списка настроек «Медиа» (только известные картинки), **тип содержимого** (`finfo`, у растровых ещё `getimagesize`), у SVG — запрет скриптов, `on*=`, `javascript:`, `foreignObject`, `ENTITY`. Имя файла — латиница (`OutputFilter::stringURLSafe`), при совпадении `-2`, `-3`…; подпапка очищается от всего, кроме `a–z 0–9 _ -` (обход каталогов невозможен). Файл сохраняется в `images/dharmashop/<папка>/<год>/`; ответ — JSON `{success, data: {path, width, height}}`. Значение поля — путь относительно сайта (без служебного хвоста, как у штатного Media).

Оформление: пунктирная область с иконкой и подсказкой (допустимые форматы и предел размера), подсветка при перетаскивании, предпросмотр внутри области; под ней строка «путь к файлу (только чтение) · Выбрать с сервера · ×». Цвета берутся от текста темы (`currentColor`) и цвета ссылок шаблона, поэтому одинаково выглядят в светлой и тёмной теме (переменных Bootstrap 5.3 в Atum нет — на них не опираемся).

Вставка из буфера обмена: щелчок по области ставит на неё фокус (окно выбора файла не открывается), затем Ctrl+V / ⌘V загружает картинку из буфера (скриншот получает имя `clipboard-<время>.png`); есть и кнопки-ссылки «выберите на устройстве» и «вставьте из буфера» (Clipboard API, браузер может спросить разрешение).

«Выбрать с сервера»: внутри поля спрятано штатное поле Media (подкласс `MediaField` открывает его `getInput()`), кнопка нажимает его «Выбрать» и следит только за **значением** штатного поля, копируя выбранный путь; поэтому внутреннее устройство окна выбора файла Joomla (и его смена между версиями) нас не касается. Загрузка — перетаскиванием на область, кликом по области (выбор файла на устройстве).

Удаление файлов (`Image\ImageCleaner`, вызывается при удалении записи): удаляются **только файлы внутри `images/dharmashop/`** (загруженные нашим загрузчиком; выбранные с сервера из других папок, например `images/sampledata`, не трогаются никогда) и **только если на них больше не ссылается** другая запись магазина (категория/бренд/бейдж — по `params.image` и `params.icon`, фото товара, обложка). Замена или снятие картинки в форме файл не удаляет (вопрос F11).

Папка загрузок и структура — тоже в настройках, вкладка «Изображения»: `image_base_folder` (внутри `images/`, латиница/цифры/`-`/`_`, вложенность через `/`, пусто → `dharmashop`; `ImageUploader::baseFolder()`) и `image_folder_mode` — `type_year` (по умолчанию: `<папка>/<тип>/<год>/`), `type_year_month`, `type`, `flat`. `ImageCleaner` удаляет файлы внутри текущей папки загрузок и прежней `images/dharmashop/` (если папку меняли); остальное не трогает никогда. Уже загруженные файлы не переносятся.

Имя сохранённого файла задаётся в настройках магазина, вкладка «Изображения» (C15): `image_name_mode` — `random` (по умолчанию, 10 символов hex), `original_random` (читаемое имя + хвост), `original` (транслитерация исходного, при совпадении добавляется номер); `image_name_prefix` — необязательный префикс (латиница, цифры, `-`, `_`). Так у разных категорий и товаров не бывает одинаковых имён (в том числе у вставленных из буфера картинок).

Галерея товаров (этап 2) использует тот же загрузчик в строках подформы.

### 5.16 Правила оформления заказа и их наследование (реализовано на этапе 1, C14)

`Order\OrderRules`: `forCategory($catid)` / `forProduct($product)` возвращают итоговые значения и источник каждого (товар, номер категории, `settings`, встроенное). Цепочка: поля `products.*` (NULL = наследовать) → `params.order_*` основной категории → её родителей (до корня) → настройки компонента `order_*` → встроенные (мин. 1, шаг 1, макс. 0, единица «шт», учёт остатков и продажа без остатка выключены). Количество и единица вычисляются при чтении, поэтому изменение категории или настроек не требует пересчёта. **Учёт остатков и «продавать без остатка» материализуются** в `variants.stock_tracked/backorder` (от них зависит `in_stock` в списках): при сохранении товара, при сохранении категории (ветка, до 5000 товаров, `materializeCategories` → `rebuildCategories` → `refreshCategories`) и командой обслуживания «Пересчитать цену и наличие». Подсказки «Наследуется: …» ставит `Order\InheritHints` через `preprocessForm` (в форме категории — без её собственных значений, у новой — по родителю).

### 5.17 SEO записей (реализовано для категорий, брендов и бейджей, C16)

Поля вкладки «SEO» (форма `category.xml`, fieldset `seo`): `params.meta_title`, `categories.metadesc`, `params.meta_image`, `metadata.robots`, `params.breadcrumb_title`, `params.h1`. `Seo\SeoSettings` отдаёт шаблон типа страницы из настроек компонента (`seo_<тип>_<title|description|h1|breadcrumb>`, пока не сохранялись — встроенные), `robots()`, `openGraph()`, `defaultImage()`; `Seo\SeoHints` подставляет в пустые поля формы подсказку «Из компонента (шаблон)». Подстановка значений в шаблон и вывод тегов — в сайтовой части (виды категорий, брендов, бейджей, товара): приоритет «поле записи → шаблон из настроек»; для картинки «поле записи → изображение записи → изображение по умолчанию».

## 6. События

Имена свои, префикс `onDharmaShop`. Каждое — типизированный класс события, аргументы читаются геттерами, изменяемое — через сеттеры. Подписчики — `SubscriberInterface`. Группа плагинов загружается по назначению (`PluginHelper::importPlugin`).

### 6.1 Каталог (группа `dharmashop`)

| Событие | Когда | Что можно менять |
|---|---|---|
| `onDharmaShopProductBeforeSave` / `AfterSave` | сохранение товара вместе с вариантами и полями (форма и CLI) | данные; отмена с ошибкой |
| `onDharmaShopProductBeforeDelete` / `AfterDelete` | удаление | отмена |
| `onDharmaShopVariantBeforeSave` / `AfterSave` | сохранение варианта | данные; отмена |
| `onDharmaShopProductPrepareForm` | сборка формы товара (поверх `onContentPrepareForm`) | вкладки, поля |
| `onDharmaShopListingQuery` | перед запросом листинга на сайте | условия, сортировки, join |
| `onDharmaShopProductPrepare` | карточка или плитка готовится к выводу | бейджи, данные, блоки |
| `onDharmaShopProductDisplay` | вывод по зонам (`beforeTitle`, `afterPrice`, `afterBuy`, `afterDescription`, `tabs`) | HTML зоны |
| `onDharmaShopPriceResolve` | расчёт эффективной цены варианта | цена (группы, акции, скидки) |
| `onDharmaShopStockChange` | любое движение остатка | — |
| `onDharmaShopIndexRebuild` | пересборка индекса и поиска товара | дополнительные данные индекса |

### 6.2 Поля (группа `dharmashopfield`)

| Событие | Назначение |
|---|---|
| `onDharmaShopFieldTypesCollect` | регистрация типов (id, подпись, `store`, XML настроек) |
| `onDharmaShopFieldFormElement` | XML-элемент редактирования значения |
| `onDharmaShopFieldValueEncode` / `Decode` | значение формы ↔ строки хранилища |
| `onDharmaShopFieldFilterElement` / `FilterApply` | элемент фильтра и условие запроса |
| `onDharmaShopFieldRender` | вывод значения на сайте |

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
| `onDharmaShopOrderBeforeCreate` / `AfterCreate` | подготовка и реакция |
| `onDharmaShopOrderStatusChanged` | смена статуса |
| `onDharmaShopPaymentStart` | плагин формирует ссылку/форму платежа |
| `onDharmaShopPaymentCallback` | входящее уведомление платёжной системы (идемпотентно по `external_id`) |
| `onDharmaShopNotifyCollectChannels` / `OrderNotify` | каналы уведомлений (почта — ядро, прочие — плагины `dharmashopnotify`) |

### 6.5 Служебные

| Событие | Назначение |
|---|---|
| `onDharmaShopSettingsPrepareForm` | вкладки настроек от плагинов |
| `onDharmaShopAdminToolsCollect` | ссылки расширений на панели инструментов |

События этапов 2+ проектируются по мере реализации; имена из списка резервируются сейчас. Статусы, подстановки `{область.имя}` и шорткоды — в `docs/SHORTCODES.md`.

## 7. Админка и сайт

### 7.1 Меню админки

Заказы · Корзины · Покупатели · Товары · Категории · Бренды · Бейджи (три пункта на один наш вид `categories` с параметром `extension=com_dharmashop`, `.brand`, `.badge`) · Порядок товаров · Поля · Группы полей · Статусы заказов · Способы доставки · Способы оплаты · Настройки (штатный `com_config`). «Аналитика» — отдельный компонент. Прайс-листы и склады в меню в v1 не выводятся (в БД они есть).

Списки — `searchtools`, сортировка по столбцам, штатная постраничность, **пакетная обработка** (по образцу RadicalMart: блок-карточка = заголовок, пояснение, значение и при необходимости «Действие» — пропустить / добавить / убрать / заменить набор): окно Joomla (`popupButton` + `<template id="joomla-dialog-batch">`), блоки описаны XML-формами `forms/batch_product.xml` и `forms/batch_category.xml` (fieldset внутри группы `batch/<операция>`, поля `value` и `action`), вид собирает окно по форме, шаблон `default_batch_body.php` один на все блоки; операции — строки `$batch_commands` в `ProductModel`/`CategoryModel` и методы `batch<Имя>` (штатный `AdminModel::batch`), блоки с действием «Пропустить» отбрасываются до вызова. У товаров: основная категория (перенести), дополнительные категории и бейджи (добавить / убрать / заменить набор), бренд (задать / снять); у категорий/брендов/бейджей: доступ, родитель того же типа (с перестройкой индекса), «Показывать в списке», сортировка и число товаров на странице (у бейджей только доступ). Чтобы добавить блок: fieldset в форме + строка в `$batch_commands` + метод. изменения таксономии идут через `Indexer::setCategories`, права и адрес при переносе — через `ProductTable::store`. «Порядок товаров» — штатный список с перетаскиванием по выбранной категории, пишет `sort_order` индекса.

Форма товара — вкладки (`uitab`): «Основное» (название, алиас, категории, описание), «Цена и остаток» (для одного варианта) или «Варианты» (подформа), «Поля» (поля товара, в том числе галерея), «SEO», «Публикация».

### 7.2 Права

`access.xml`: штатные `core.*` + `dharmashop.orders.manage`, `dharmashop.orders.status`, `dharmashop.customers.view`, `dharmashop.settings`.

### 7.3 Сайт

Виды: `categories`, `category` (список + фильтр), `product`, `search`, `cart`, `checkout`, `order` (по `secret`), `orders` (кабинет). Маршрутизатор — `RouterView` с `MenuRules`/`StandardRules`/`NomenuRules`. Переопределения — в `dharma_starter` (`html/com_dharmashop`), стили на переменных `--dharma-*`. Корзина работает без JS (формы POST с редиректом), JS — только улучшение.

### 7.4 Модули

`categories`, `search`, `filter` (этап 1), `products` (новые/избранные/популярные), `cart` (этап 2). Фильтр-модуль и вид категории используют один `FilterBuilder`.

## 8. Письма, настройки, перенос с RadicalMart

- **Письма:** штатные шаблоны писем Joomla `com_dharmashop.order.new.customer`, `.new.admin`, `.status.customer`, редактируются в «Шаблонах писем». Текст статуса — `statuses.customer_message`.
- **Настройки (`config.xml`):** валюта (код, символ, позиция, знаков), число товаров на странице, подкатегории в списке, счётчики фильтра, срок жизни корзины, гостевое оформление, формат номера заказа, порог курсорной пагинации.
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
6. **Модули выборки товаров на каждой странице.** Сравнение с RadicalMart (`BENCHMARKS_RM_NOTES.md`): запрос «выбрать 12 товаров» без индекса просматривает всю таблицу товаров и выполняется на любой странице сайта (133 мс на 50 тыс.). Наши модули «новые/избранные/популярные» обязаны ходить по индексу витрины (`featured`, `created`, сортировочные столбцы `product_categories`) и проверяются замером «статья + модули на странице» (вклад магазина в обычные страницы должен быть близок к нулю).
