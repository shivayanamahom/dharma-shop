# Разработка: стенд, сборка, проверки

## Стенд

- Сайт: https://test.test, файлы `/Volumes/Work/Server/www/test` (чистая Joomla 6.1.4, шаблон `dharma_starter`). Переменная `DHARMA_SITE` по умолчанию указывает на него; `dharmatest.test` только для чтения.
- Для проверок по HTTP: тестовый пользователь `dharmadev` (Super Users). Логин и пароль лежат в `dev/.env` (в git не попадает; шаблон — `dev/.env.example`). Пользователя `dharma` не трогаем.
- Загрузка окружения: `set -a; . dev/.env; set +a`.

## Скрипты `dev/`

| Скрипт | Что делает |
|---|---|
| `boot.php` | поднимает консольное приложение Joomla на `DHARMA_SITE` |
| `install.php <zip>` | устанавливает пакет как «Установка расширений»; передавайте абсолютный путь к zip |
| `uninstall.php` | удаляет пакет `pkg_dharmashop` (вместе с таблицами) |
| `sync.sh` | раскладывает исходники в сайт без сборки zip (после первой установки) |
| `db.sh "SQL"` | запрос к базе сайта; `PFX_` заменяется префиксом таблиц |
| `web-login.sh` | вход в админку по HTTP, куки в `$COOKIES` (нужны `DHARMA_USER`, `DHARMA_PASS`, `DHARMA_URL`) |
| `make-category.php <extension> "<название>" [родитель]` | создаёт категорию нужного типа (`com_dharmashop`, `.brand`, `.badge`) |
| `save-product.sh "Название" <catid> <артикул> <цена> [brand] [badge]` | создаёт товар через форму админки по HTTP (нужен вход `web-login.sh`) |
| `bench/bench.php` | генератор тестового каталога и замеры (`generate`/`run`/`all`/`drop`), см. `docs/BENCHMARKS_NOTES.md` |
| `bench/report.php` | собирает `docs/BENCHMARKS.md` из `dev/bench/results/*.json` |
| `bench/rm/clone.php` | размножает товары RadicalMart на тестовом сайте (`<N>` / `clean`); `DHARMA_SITE` = сайт с RM |
| `bench/rm/run-scale.sh <N>` | клонирование + штатный пересчёт индекса RM + замеры → `dev/bench/rm/results/<N>.json` |
| `bench/rm/measure.php` | замеры страниц RM по HTTP + журнал запросов MySQL (включает его глобально на время замера и возвращает) |
| `bench/rm/report.php` | собирает `docs/BENCHMARKS_RM.md` |
| `bench/rm/dump.sh` | дамп/восстановление базы сайта перед массовыми данными |

Не запускайте два пересчёта индекса RM параллельно (дубль ключа в индексе списков). Перед клонированием снимайте дамп и после замеров возвращайте сайт в исходное состояние.

## Сборка и установка

```bash
./build.sh                                   # dist/pkg_dharmashop-<версия>.zip
DHARMA_SITE=/Volumes/Work/Server/www/test php dev/install.php "$PWD/dist/pkg_dharmashop-0.1.0.zip"
```

Версия остаётся `0.1.0` (решение B11). Новые расширения пакета добавляйте в `EXTENSIONS` в `build.sh` и в `<files>` манифеста `pkg_dharmashop.xml`.

## Правила проверки

- После изменения схемы: удалить пакет (`dev/uninstall.php`), собрать, установить, проверить число таблиц (`dev/db.sh "show tables like 'PFX_dharmashop%'"`). Пока релизов нет, схему правим прямо в `install.mysql.utf8.sql`; после первого релиза — только через `sql/updates/mysql/<версия>.sql`.
- Изменения скорости на путях витрины — прогнать `dev/bench` минимум на 100 000 товаров и обновить `docs/BENCHMARKS.md`.
- Пустые папки в манифесте компонента ломают установку (проверено: пустая `forms`).
- Данные бенчмарка лежат в таблицах `bn_*` той же базы; после замеров `php dev/bench/bench.php drop`.
