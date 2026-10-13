# Интеграция Dharma Shop с Radical Sitemap

`integrations/plg_radicalsitemap_dharmashop/` содержит отдельный Joomla-плагин группы `radicalsitemap`. Он регистрирует шаги сканирования Dharma Shop и добавляет опубликованные публичные категории, бренды, бейджи, товары и посадочные страницы. Для товаров и посадочных страниц учитывается публичный доступ ко всей ветке основной категории. Метаданные robots с `noindex` исключают URL из карты; сломанные посадочные страницы не попадают в неё. Отключённый компонент магазина или отсутствующий Radical Sitemap не создаёт шаги сканирования; посадочные страницы сканируются, если включён `com_dharmashop_landing`.

Плагин включён в `build.sh` / `pkg_dharmashop`, а также собирается отдельным ZIP скриптом `integrations/plg_radicalsitemap_dharmashop/build.sh`. Новый плагин Joomla ставит выключенным; включить его можно в **Настройки → Расширения** после установки Radical Sitemap.

Реализация использует события Radical Sitemap `onRadicalSitemapGetScanSteps` и `onScanDharmaShop*` и объект ссылки `ScanHelper::getBlankItem()`. Контракт проверен чтением исходника Radical Sitemap 0.0.7-dev; плагин не копирует код RadicalMart.

