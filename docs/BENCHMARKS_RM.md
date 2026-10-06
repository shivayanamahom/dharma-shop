<!-- Создаётся командой `php dev/bench/rm/report.php`; выводы и оговорки — в docs/BENCHMARKS_RM_NOTES.md. -->

# RadicalMart и Dharma Shop: сравнение скорости

RadicalMart 3.0.27 замерялся как «чёрный ящик»: HTTP-ответ страницы (время до первого байта, медиана 9 прогонов) и журнал запросов MySQL на время одного обращения. Код RadicalMart не читался. Dharma Shop замерялся на уровне базы (веб-страниц ещё нет), поэтому сравнивается прежде всего **время БД** и число просмотренных строк.

- Исходный каталог RadicalMart: 114 товаров, 419 строк в индексе списков; дальше он размножен клонированием существующих товаров (`dev/bench/rm/clone.php`), индекс пересобран штатными командами RadicalMart.
- «БД RM» — суммарное время всех запросов страницы по журналу MySQL (включая запросы Joomla); «Dharma БД» — время запросов нашего листинга (ключи страницы + карточки), без остального.

## 10 000 товаров

| Сценарий | RM: ответ, мс | RM: БД, мс | RM: запросов | RM: строк просмотрено | Dharma: БД, мс |
|---|---:|---:|---:|---:|---:|
| не-RM страница (статья), для вычитания базовой стоимости Joomla | 83,8 | 24,6 | 58 | 11 291 | — |
| главная | 93,0 | 24,0 | 80 | 11 555 | — |
| категория «Продукция» (корень), стр. 1 | 110,5 | 35,8 | 45 | 16 112 | 0,63 |
| корень, сортировка по цене | 108,5 | 34,9 | 45 | 16 109 | 0,55 |
| корень, сортировка по названию | 106,5 | 33,8 | 47 | 16 102 | 0,52 |
| корень, сортировка по дате (новые) | 109,5 | 34,0 | 49 | 16 123 | 0,60 |
| категория «Продукция», глубокая стр. (start=4991) | 114,6 | 40,5 | 45 | 21 108 | 0,69 |
| категория-узел «Горячие смеси» | 96,3 | 23,2 | 55 | 11 044 | 0,28 |
| категория-лист «Щебень гранитный» | 93,5 | 23,1 | 54 | 11 877 | 0,27 |
| корень + фильтр по полю «крупность = 8» | 117,6 | 54,3 | 52 | 31 872 | 0,86 |
| карточка клонированного товара | 81,3 | 22,6 | 48 | 10 744 | 0,55 |

## 50 000 товаров

| Сценарий | RM: ответ, мс | RM: БД, мс | RM: запросов | RM: строк просмотрено | Dharma: БД, мс |
|---|---:|---:|---:|---:|---:|
| не-RM страница (статья), для вычитания базовой стоимости Joomla | 207,3 | 142,1 | 53 | 51 283 | — |
| главная | 206,9 | 138,3 | 77 | 51 557 | — |
| категория «Продукция» (корень), стр. 1 | 308,2 | 243,5 | 49 | 77 015 | 1,23 |
| корень, сортировка по цене | 309,5 | 254,4 | 45 | 77 002 | 1,25 |
| корень, сортировка по названию | 301,5 | 234,5 | 49 | 77 001 | 0,57 |
| корень, сортировка по дате (новые) | 306,3 | 244,7 | 49 | 77 014 | 1,31 |
| категория «Продукция», глубокая стр. (start=24978) | 388,4 | 328,9 | 49 | 102 000 | 2,56 |
| категория-узел «Горячие смеси» | 212,2 | 140,2 | 54 | 51 637 | 0,47 |
| категория-лист «Щебень гранитный» | 226,4 | 157,7 | 50 | 56 042 | 0,29 |
| корень + фильтр по полю «крупность = 8» | 527,5 | 476,2 | 48 | 155 445 | 3,43 |
| карточка клонированного товара | 196,8 | 137,4 | 49 | 50 756 | 0,56 |

## Для сравнения: исходный каталог RadicalMart (114 товаров)

| Сценарий | Ответ, мс | БД, мс | Запросов | Строк просмотрено |
|---|---:|---:|---:|---:|
| не-RM страница (статья), для вычитания базовой стоимости Joomla | 61,9 | 5,0 | 62 | 1 413 |
| главная | 72,5 | 5,1 | 88 | 1 701 |
| категория «Продукция» (корень), стр. 1 | 73,8 | 3,8 | 51 | 1 059 |
| корень, сортировка по цене | 74,8 | 3,7 | 53 | 1 060 |
| корень, сортировка по названию | 75,1 | 3,6 | 53 | 1 063 |
| корень, сортировка по дате (новые) | 74,4 | 3,6 | 55 | 1 072 |
| категория «Продукция», глубокая стр. (start=46) | 75,6 | 4,2 | 55 | 1 113 |
| категория-узел «Горячие смеси» | 74,8 | 3,9 | 59 | 1 011 |
| категория-лист «Щебень гранитный» | 64,0 | 3,5 | 54 | 921 |
| корень + фильтр по полю «крупность = 8» | 65,2 | 4,0 | 54 | 1 274 |

## Самые тяжёлые запросы RadicalMart (по журналу MySQL)

### 50 000 товаров

**категория «Продукция» (корень), стр. 1**

- 132,8 мс, просмотрено 50 011 строк: `SELECT p.id,p.title,p.alias,p.code,p.category,p.category_pathway,p.category_route,p.categories_additional,p.categories_all,p.created,p.introtext,p.prices,p.stoc…`
- 94,3 мс, просмотрено 26 156 строк: `SELECT COUNT(*) FROM ol4oe_radicalmart_categories_items AS ci WHERE ci.state = 1 AND ci.category_id = 2;…`
- 9,3 мс, просмотрено 1 строк: `UPDATE ol4oe_session SET data = 'joomla|s:700:\"TzoyNDoiSm9vbWxhXFJlZ2lzdHJ5XFJlZ2lzdHJ5IjozOntzOjc6IgAqAGRhdGEiO086ODoic3RkQ2xhc3MiOjM6e3M6Nzoic2Vzc2lvbiI7…`

**корень + фильтр по полю «крупность = 8»**

- 204,9 мс, просмотрено 52 316 строк: `SELECT COUNT(*) FROM ol4oe_radicalmart_categories_items AS ci WHERE ci.state = 1 AND ci.category_id = 2 AND ( SELECT 1 FROM ol4oe_radicalmart_categ…`
- 138,3 мс, просмотрено 50 011 строк: `SELECT p.id,p.title,p.alias,p.code,p.category,p.category_pathway,p.category_route,p.categories_additional,p.categories_all,p.created,p.introtext,p.prices,p.stoc…`
- 129,3 мс, просмотрено 52 316 строк: `SELECT ci.item_id FROM ol4oe_radicalmart_categories_items AS ci WHERE ci.state = 1 AND ci.category_id = 2 AND ( SELECT 1 FROM ol4oe_radicalmart_cat…`

**не-RM страница (статья), для вычитания базовой стоимости Joomla**

- 136,5 мс, просмотрено 50 011 строк: `SELECT p.id,p.title,p.alias,p.code,p.category,p.category_pathway,p.category_route,p.categories_additional,p.categories_all,p.created,p.introtext,p.prices,p.stoc…`
- 0,6 мс, просмотрено 0 строк: `INSERT INTO ol4oe_session (session_id,guest,time,userid,username,client_id) VALUES ('6a11bf16d3ed7c16cb8d203a6a43fd60', 1, '1791288067', 0, '', 0)…`
- 0,6 мс, просмотрено 1 строк: `UPDATE ol4oe_session SET data = 'joomla|s:700:\"TzoyNDoiSm9vbWxhXFJlZ2lzdHJ5XFJlZ2lzdHJ5IjozOntzOjc6IgAqAGRhdGEiO086ODoic3RkQ2xhc3MiOjM6e3M6Nzoic2Vzc2lvbiI7…`

### 10 000 товаров

**категория «Продукция» (корень), стр. 1**

- 19,2 мс, просмотрено 10 011 строк: `SELECT p.id,p.title,p.alias,p.code,p.category,p.category_pathway,p.category_route,p.categories_additional,p.categories_all,p.created,p.introtext,p.prices,p.stoc…`
- 12,5 мс, просмотрено 5 261 строк: `SELECT COUNT(*) FROM ol4oe_radicalmart_categories_items AS ci WHERE ci.state = 1 AND ci.category_id = 2;…`
- 0,8 мс, просмотрено 24 строк: `SELECT ci.item_id FROM ol4oe_radicalmart_categories_items AS ci WHERE ci.state = 1 AND ci.category_id = 2 ORDER BY ci.in_stock DESC,ci.orderi…`

**корень + фильтр по полю «крупность = 8»**

- 24,4 мс, просмотрено 10 011 строк: `SELECT p.id,p.title,p.alias,p.code,p.category,p.category_pathway,p.category_route,p.categories_additional,p.categories_all,p.created,p.introtext,p.prices,p.stoc…`
- 16,9 мс, просмотрено 10 526 строк: `SELECT ci.item_id FROM ol4oe_radicalmart_categories_items AS ci WHERE ci.state = 1 AND ci.category_id = 2 AND ( SELECT 1 FROM ol4oe_radicalmart_cat…`
- 9,6 мс, просмотрено 10 526 строк: `SELECT COUNT(*) FROM ol4oe_radicalmart_categories_items AS ci WHERE ci.state = 1 AND ci.category_id = 2 AND ( SELECT 1 FROM ol4oe_radicalmart_categ…`

**не-RM страница (статья), для вычитания базовой стоимости Joomla**

- 19,6 мс, просмотрено 10 011 строк: `SELECT p.id,p.title,p.alias,p.code,p.category,p.category_pathway,p.category_route,p.categories_additional,p.categories_all,p.created,p.introtext,p.prices,p.stoc…`
- 0,8 мс, просмотрено 1 строк: `UPDATE ol4oe_session SET data = 'joomla|s:700:\"TzoyNDoiSm9vbWxhXFJlZ2lzdHJ5XFJlZ2lzdHJ5IjozOntzOjc6IgAqAGRhdGEiO086ODoic3RkQ2xhc3MiOjM6e3M6Nzoic2Vzc2lvbiI7…`
- 0,6 мс, просмотрено 0 строк: `INSERT INTO ol4oe_session (session_id,guest,time,userid,username,client_id) VALUES ('27c0ccf214929a17cefad9a419710fbc', 1, '1791287203', 0, '', 0)…`

