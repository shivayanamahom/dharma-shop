<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * Установочный скрипт пакета: проверяет окружение и включает плагины пакета.
 *
 * @package    pkg_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            InstallerScriptInterface::class,
            new class (Factory::getApplication(), $container->get(DatabaseInterface::class)) implements InstallerScriptInterface {
                private const MIN_JOOMLA = '6.0.0';

                private const MIN_PHP = '8.3.0';

                /** Плагины пакета, которые включаем сразу после установки: [группа, элемент]. */
                private const PLUGINS = [['dharmashop', 'imageresize'], ['dharmashopnotify', 'email'], ['dharmashopnotify', 'telegram'], ['dharmashop', 'social'], ['dharmashop', 'structureddata'], ['dharmashopfield', 'basic'], ['dharmashopshipping', 'standard'], ['dharmashopshipping', 'courier'], ['dharmashopshipping', 'pickup'], ['dharmashoppayment', 'standard'], ['dharmashoppromotion', 'coupon'], ['system', 'dharmashopswiper'], ['system', 'dharmashopfancybox'], ['system', 'dharmashopmenu'], ['system', 'dharmashoppositions'], ['system', 'dharmashoprelated'], ['task', 'dharmashop'], ['task', 'dharmashopexport'], ['console', 'dharmashopexport'], ['dharmashopexport', 'xlsx'], ['dharmashopexport', 'yml'], ['dharmashopimport', 'xlsx']];

                /** Обновление не должно включать плагин, отключённый владельцем сайта. */
                private array $existingPlugins = [];

                /** Снимок переноса C32 сохраняется, если установка завершилась ошибкой. */
                private ?string $layoutMigrationBackup = null;

                private int $layoutMigrationConflicts = 0;

                public function __construct(
                    private readonly CMSApplicationInterface $app,
                    private readonly DatabaseInterface $db,
                ) {
                }

                public function install(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                public function update(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                public function uninstall(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                public function preflight(string $type, InstallerAdapter $adapter): bool
                {
                    if ($type === 'uninstall') {
                        return true;
                    }

                    foreach (self::PLUGINS as [$group, $element]) {
                        $query = $this->db->createQuery()->select('extension_id')->from($this->db->quoteName('#__extensions'))
                            ->where('type = ' . $this->db->quote('plugin'))->where('folder = ' . $this->db->quote($group))
                            ->where('element = ' . $this->db->quote($element));
                        if ($this->db->setQuery($query)->loadResult()) {
                            $this->existingPlugins[$group . '/' . $element] = true;
                        }
                    }

                    if (version_compare(PHP_VERSION, self::MIN_PHP, '<')) {
                        $this->app->enqueueMessage(sprintf('Dharma Shop требует PHP %s или новее.', self::MIN_PHP), 'error');

                        return false;
                    }

                    if (version_compare(JVERSION, self::MIN_JOOMLA, '<')) {
                        $this->app->enqueueMessage(sprintf('Dharma Shop требует Joomla %s или новее.', self::MIN_JOOMLA), 'error');

                        return false;
                    }

                    try {
                        $tables = $this->db->getTableList();
                        if (in_array($this->db->replacePrefix('#__dharmashop_products'), $tables, true)) {
                            $invalid = (int) $this->db->setQuery('SELECT COUNT(*) FROM (SELECT p.id FROM #__dharmashop_products p LEFT JOIN #__dharmashop_variants v ON v.product_id=p.id GROUP BY p.id HAVING COUNT(v.id)<>1) invalid')->loadResult();
                            if ($invalid) { throw new \RuntimeException('Обновление остановлено: у каждого товара должен быть ровно один служебный SKU.'); }
                        }
                        $this->upgradeFieldTargets();
                        $this->upgradeFieldGroupTabs();
                        $this->upgradeFieldValueStorage();
                        $this->upgradePromotionStorage();
                        $this->prepareLayoutMigration();
                    } catch (\Throwable $error) {
                        $this->app->enqueueMessage('Dharma Shop: ' . $error->getMessage()
                            . ($this->layoutMigrationBackup !== null ? '. Снимок переноса: ' . $this->layoutMigrationBackup : ''), 'error');

                        return false;
                    }

                    return true;
                }

                /** Акции добавлены после первой установки: при обновлении пакета создаём их схему до установки новых файлов. */
                private function upgradePromotionStorage(): void
                {
                    $tables = $this->db->getTableList();
                    if (!in_array($this->db->replacePrefix('#__dharmashop_carts'), $tables, true)) return;
                    $prefix = $this->db->getPrefix();
                    $this->db->setQuery("CREATE TABLE IF NOT EXISTS `{$prefix}dharmashop_promotions` (
                        `id` int unsigned NOT NULL AUTO_INCREMENT, `title` varchar(255) NOT NULL, `plugin` varchar(50) NOT NULL,
                        `code` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL, `state` tinyint NOT NULL DEFAULT 0,
                        `access` int unsigned NOT NULL DEFAULT 1, `ordering` int NOT NULL DEFAULT 0, `starts` datetime NULL, `ends` datetime NULL,
                        `params` text NULL, `checked_out` int unsigned NULL, `checked_out_time` datetime NULL, PRIMARY KEY (`id`),
                        UNIQUE KEY `uq_code` (`code`), KEY `idx_state_period` (`state`,`starts`,`ends`,`id`), KEY `idx_ordering` (`ordering`,`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci")->execute();
                    $this->db->setQuery("CREATE TABLE IF NOT EXISTS `{$prefix}dharmashop_promotion_codes` (
                        `id` int unsigned NOT NULL AUTO_INCREMENT, `promotion_id` int unsigned NOT NULL,
                        `code` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL, `params` text NULL,
                        `ordering` int NOT NULL DEFAULT 0, PRIMARY KEY (`id`), UNIQUE KEY `uq_code` (`code`),
                        KEY `idx_promotion_ordering` (`promotion_id`,`ordering`,`id`),
                        CONSTRAINT `fk_dhshop_promotion_code_promotion` FOREIGN KEY (`promotion_id`) REFERENCES `{$prefix}dharmashop_promotions` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci")->execute();
                    $this->db->setQuery("CREATE TABLE IF NOT EXISTS `{$prefix}dharmashop_cart_promotions` (
                        `cart_id` int unsigned NOT NULL, `promotion_id` int unsigned NOT NULL, `promotion_code_id` int unsigned NULL,
                        `code` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL, `applied` datetime NOT NULL,
                        PRIMARY KEY (`cart_id`), KEY `idx_promotion` (`promotion_id`), KEY `idx_promotion_code` (`promotion_code_id`),
                        CONSTRAINT `fk_dhshop_cart_promotion_cart` FOREIGN KEY (`cart_id`) REFERENCES `{$prefix}dharmashop_carts` (`id`) ON DELETE CASCADE,
                        CONSTRAINT `fk_dhshop_cart_promotion_rule` FOREIGN KEY (`promotion_id`) REFERENCES `{$prefix}dharmashop_promotions` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci")->execute();
                    $this->db->setQuery("CREATE TABLE IF NOT EXISTS `{$prefix}dharmashop_promotion_uses` (
                        `id` bigint unsigned NOT NULL AUTO_INCREMENT, `promotion_id` int unsigned NOT NULL, `promotion_code_id` int unsigned NULL, `order_id` int unsigned NOT NULL,
                        `customer_id` int unsigned NULL, `customer_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
                        `code` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL, `amount` decimal(15,2) NOT NULL,
                        `created` datetime NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `uq_order_promotion` (`order_id`,`promotion_id`),
                        KEY `idx_promotion_created` (`promotion_id`,`created`), KEY `idx_promotion_customer` (`promotion_id`,`customer_hash`),
                        KEY `idx_promotion_code_customer` (`promotion_code_id`,`customer_hash`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci")->execute();
                    $cartColumns = $this->db->getTableColumns('#__dharmashop_cart_promotions');
                    if (!isset($cartColumns['promotion_code_id'])) {
                        $this->db->setQuery("ALTER TABLE `{$prefix}dharmashop_cart_promotions` ADD `promotion_code_id` int unsigned NULL AFTER `promotion_id`, ADD KEY `idx_promotion_code` (`promotion_code_id`)")->execute();
                    }
                    $useColumns = $this->db->getTableColumns('#__dharmashop_promotion_uses');
                    if (!isset($useColumns['promotion_code_id'])) {
                        $this->db->setQuery("ALTER TABLE `{$prefix}dharmashop_promotion_uses` ADD `promotion_code_id` int unsigned NULL AFTER `promotion_id`, ADD KEY `idx_promotion_code_customer` (`promotion_code_id`,`customer_hash`)")->execute();
                    }
                    // Переносит коды из прежней модели «одна запись на код» в повторяемые строки.
                    $this->db->setQuery("INSERT IGNORE INTO `{$prefix}dharmashop_promotion_codes` (`promotion_id`,`code`,`params`,`ordering`) SELECT `id`,`code`,`params`,`ordering` FROM `{$prefix}dharmashop_promotions` WHERE `plugin`='coupon' AND `code` IS NOT NULL")->execute();
                    $this->db->setQuery("UPDATE `{$prefix}dharmashop_cart_promotions` cp JOIN `{$prefix}dharmashop_promotion_codes` pc ON pc.promotion_id=cp.promotion_id AND pc.code=cp.code SET cp.promotion_code_id=pc.id WHERE cp.promotion_code_id IS NULL")->execute();
                    $this->db->setQuery("UPDATE `{$prefix}dharmashop_promotion_uses` pu JOIN `{$prefix}dharmashop_promotion_codes` pc ON pc.promotion_id=pu.promotion_id AND pc.code=pu.code SET pu.promotion_code_id=pc.id WHERE pu.promotion_code_id IS NULL")->execute();
                    $this->db->setQuery("UPDATE `{$prefix}dharmashop_promotions` SET `code`=NULL, `params`='{}' WHERE `plugin`='coupon' AND `code` IS NOT NULL")->execute();
                }

                public function postflight(string $type, InstallerAdapter $adapter): bool
                {
                    if ($type === 'uninstall') {
                        return true;
                    }

                    try {
                        $this->finishLayoutMigration();
                    } catch (\Throwable $error) {
                        $this->app->enqueueMessage('Dharma Shop: ' . $error->getMessage()
                            . '. Снимок переноса: ' . $this->layoutMigrationBackup, 'error');

                        return false;
                    }

                    foreach (self::PLUGINS as [$group, $element]) {
                        if (isset($this->existingPlugins[$group . '/' . $element])) {
                            continue;
                        }
                        $query = $this->db->getQuery(true)
                            ->update($this->db->quoteName('#__extensions'))
                            ->set($this->db->quoteName('enabled') . ' = 1')
                            ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('plugin'))
                            ->where($this->db->quoteName('folder') . ' = ' . $this->db->quote($group))
                            ->where($this->db->quoteName('element') . ' = ' . $this->db->quote($element));

                        $this->db->setQuery($query)->execute();
                    }

                    require_once JPATH_ADMINISTRATOR . '/components/com_dharmashop/src/Fields/FileStorage.php';
                    \Dharma\Component\DharmaShop\Administrator\Fields\FileStorage::upgrade($this->db);
                    require_once JPATH_ADMINISTRATOR . '/components/com_dharmashop/src/Catalog/GroupStorage.php';
                    \Dharma\Component\DharmaShop\Administrator\Catalog\GroupStorage::upgrade($this->db);
                    require_once JPATH_ADMINISTRATOR . '/components/com_dharmashop/src/Filter/FilterStorage.php';
                    \Dharma\Component\DharmaShop\Administrator\Filter\FilterStorage::upgrade($this->db);
                    $this->ensureDefaultCategory();
                    $this->ensureInitialOrderStatus();
                    // При первой установке PSR-4 карта нового компонента может ещё не обновиться.
                    $customerSource = JPATH_ADMINISTRATOR . '/components/com_dharmashop/src';
                    require_once $customerSource . '/Support/Sql.php';
                    require_once $customerSource . '/Customer/ContactKey.php';
                    require_once $customerSource . '/Customer/CustomerStorage.php';
                    \Dharma\Component\DharmaShop\Administrator\Customer\CustomerStorage::upgrade($this->db);
                    $ddl = file_get_contents(JPATH_ADMINISTRATOR . '/components/com_dharmashop/sql/notifications.mysql.utf8.sql');
                    $this->db->setQuery($ddl)->execute();

                    $this->ensureAclVersion();

                    if ($type === 'install') {
                        $this->ensureDefaultBadges();
                        require_once JPATH_ADMINISTRATOR . '/components/com_dharmashop/src/Installer/DefaultSettings.php';
                        \Dharma\Component\DharmaShop\Administrator\Installer\DefaultSettings::apply($this->db);
                        $this->app->enqueueMessage(Text::_('PKG_DHARMASHOP_INSTALLED'), 'info');
                    }

                    return true;
                }

                /**
                 * Версия модели прав (`acl_version` в параметрах компонента). Все сборки с отдельными правами уже перенесли права при выходе C90,
                 * поэтому отсутствие версии — не повод переносить права заново: иначе после каждого обновления группы, у которых владелец
                 * отозвал право, получали бы его снова (признак «действие есть в правилах» теряется при любом сохранении прав в настройках).
                 * Следующий перенос прав привязывается к повышению версии (`AclMigration::VERSION`), а не к содержимому правил.
                 */
                private function ensureAclVersion(): void
                {
                    $where = [$this->db->quoteName('type') . ' = ' . $this->db->quote('component'), $this->db->quoteName('element') . ' = ' . $this->db->quote('com_dharmashop')];
                    $params = json_decode((string) $this->db->setQuery($this->db->getQuery(true)->select($this->db->quoteName('params'))->from($this->db->quoteName('#__extensions'))->where($where))->loadResult(), true);
                    $params = is_array($params) ? $params : [];

                    if (isset($params['acl_version'])) {
                        return;
                    }

                    $params['acl_version'] = 1;
                    $this->db->setQuery($this->db->getQuery(true)->update($this->db->quoteName('#__extensions'))
                        ->set($this->db->quoteName('params') . ' = ' . $this->db->quote(json_encode($params, JSON_UNESCAPED_UNICODE)))->where($where))->execute();
                }

                /** C73: только пустой справочник, не меняем уже настроенные статусы магазина. */
                private function ensureInitialOrderStatus(): void
                {
                    $db = $this->db;
                    $table = $db->quoteName('#__dharmashop_statuses');
                    if ((int) $db->setQuery('SELECT COUNT(*) FROM ' . $table)->loadResult() > 0) return;
                    $language = $this->app->getLanguage();
                    $language->load('com_dharmashop', JPATH_ADMINISTRATOR);
                    $title = Text::_('COM_DHARMASHOP_ORDER_STATUS_NEW');
                    $query = $db->createQuery()->insert($table)->columns(['title','alias','color','is_default','stock_effect','ordering','state'])
                        ->values(':title,' . $db->quote('new') . ',' . $db->quote('info') . ',1,0,1,1')->bind(':title',$title);
                    $db->setQuery($query)->execute();
                    // Остальные стандартные статусы (в обработке, оплачен, отправлен, выполнен, отменён…) — тоже только в пустой справочник.
                    require_once JPATH_ADMINISTRATOR . '/components/com_dharmashop/src/Order/DefaultStatuses.php';
                    \Dharma\Component\DharmaShop\Administrator\Order\DefaultStatuses::install($db);
                }

                /** C50: аддитивное обновление существующей схемы без деинсталляции и удаления каталога. */
                private function upgradeFieldTargets(): void
                {
                    $tables = $this->db->getTableList();

                    foreach (['#__dharmashop_fieldgroups', '#__dharmashop_fields'] as $table) {
                        $name = $this->db->replacePrefix($table);

                        // При первой установке таблицы создаст install.mysql.utf8.sql компонента.
                        if (!in_array($name, $tables, true)) {
                            continue;
                        }

                        $columns = $this->db->getTableColumns($name, false);

                        if (!isset($columns['target'])) {
                            $this->db->setQuery('ALTER TABLE ' . $this->db->quoteName($name)
                                . " ADD COLUMN `target` varchar(16) NOT NULL DEFAULT 'product' AFTER `id`")
                                ->execute();
                        }
                    }
                }

                /** C59: additive category storage and second numeric range bound. */
                private function upgradeFieldValueStorage(): void
                {
                    $name = $this->db->replacePrefix('#__dharmashop_field_values');
                    if (!in_array($name, $this->db->getTableList(), true)) { return; }
                    $columns = $this->db->getTableColumns($name, false);
                    if (!isset($columns['num_end'])) {
                        $this->db->setQuery('ALTER TABLE ' . $this->db->quoteName($name)
                            . ' ADD COLUMN `num_end` decimal(18,6) NULL AFTER `num`, ADD KEY `idx_num_end` (`field_id`,`num_end`,`product_id`)')->execute();
                    }
                    $this->db->setQuery('CREATE TABLE IF NOT EXISTS `#__dharmashop_category_field_values` (
                        `id` bigint unsigned NOT NULL AUTO_INCREMENT, `category_id` int unsigned NOT NULL,
                        `field_id` int unsigned NOT NULL, `option_id` int unsigned NULL,
                        `num` decimal(18,6) NULL, `num_end` decimal(18,6) NULL, `str` varchar(190) NULL,
                        PRIMARY KEY (`id`), KEY `idx_category` (`category_id`,`field_id`),
                        KEY `idx_option` (`field_id`,`option_id`,`category_id`), KEY `idx_num` (`field_id`,`num`,`category_id`),
                        KEY `idx_num_end` (`field_id`,`num_end`,`category_id`), KEY `idx_str` (`field_id`,`str`,`category_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci')->execute();
                    $this->db->setQuery('CREATE TABLE IF NOT EXISTS `#__dharmashop_category_field_texts` (
                        `id` bigint unsigned NOT NULL AUTO_INCREMENT, `category_id` int unsigned NOT NULL,
                        `field_id` int unsigned NOT NULL, `value` mediumtext NULL,
                        PRIMARY KEY (`id`), KEY `idx_category` (`category_id`,`field_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci')->execute();
                }

                /** C51: способ размещения групп в форме; сохраняет все существующие записи. */
                private function upgradeFieldGroupTabs(): void
                {
                    $name = $this->db->replacePrefix('#__dharmashop_fieldgroups');

                    if (!in_array($name, $this->db->getTableList(), true)) {
                        return;
                    }

                    $columns = $this->db->getTableColumns($name, false);

                    if (!isset($columns['tab_mode'])) {
                        $this->db->setQuery('ALTER TABLE ' . $this->db->quoteName($name)
                            . " ADD COLUMN `tab_mode` varchar(16) NOT NULL DEFAULT 'fields' AFTER `target`")
                            ->execute();
                    }
                }

                /** Сохраняет старые файлы до удаления папки layouts установщиком компонента. */
                private function prepareLayoutMigration(): void
                {
                    $legacy = JPATH_ROOT . '/components/com_dharmashop/layouts';
                    if (!is_dir($legacy)) {
                        return;
                    }

                    $legacyFiles = $this->layoutFiles($legacy);
                    $shared = JPATH_ROOT . '/layouts/components/dharmashop';
                    $sharedFiles = is_dir($shared) ? $this->layoutFiles($shared) : [];
                    $temporary = rtrim((string) $this->app->get('tmp_path', JPATH_ROOT . '/tmp'), '/\\');
                    $this->layoutMigrationBackup = $temporary . '/dharmashop-layouts-' . bin2hex(random_bytes(12));

                    $this->copyLayoutFiles($legacy, $this->layoutMigrationBackup . '/legacy', $legacyFiles);
                    $this->copyLayoutFiles($shared, $this->layoutMigrationBackup . '/shared', $sharedFiles);

                    foreach ($legacyFiles as $relative) {
                        if (is_file($shared . '/' . $relative)
                            && hash_file('sha256', $legacy . '/' . $relative) !== hash_file('sha256', $shared . '/' . $relative)) {
                            $this->layoutMigrationConflicts++;
                        }
                    }

                    // Старые переопределения сохраняются; существующие новые имеют приоритет.
                    foreach (glob(JPATH_ROOT . '/templates/*', GLOB_ONLYDIR) ?: [] as $template) {
                        $old = $template . '/html/layouts/com_dharmashop';
                        if (is_dir($old)) {
                            $this->copyLayoutFiles(
                                $old,
                                $template . '/html/layouts/components/dharmashop',
                                $this->layoutFiles($old),
                                false,
                            );
                        }
                    }
                }

                /** Переносит сохранённые файлы после установки файлового расширения и компонента. */
                private function finishLayoutMigration(): void
                {
                    if ($this->layoutMigrationBackup === null) {
                        return;
                    }

                    $target = JPATH_ROOT . '/layouts/components/dharmashop';
                    foreach (['legacy', 'shared'] as $source) {
                        $directory = $this->layoutMigrationBackup . '/' . $source;
                        if (is_dir($directory)) {
                            $this->copyLayoutFiles($directory, $target, $this->layoutFiles($directory));
                        }
                    }

                    if ($this->layoutMigrationConflicts > 0) {
                        $this->app->enqueueMessage(
                            'Dharma Shop: сохранены существующие общие макеты. Конфликтующие старые файлы доступны в ' . $this->layoutMigrationBackup,
                            'warning',
                        );
                    } elseif (is_dir($this->layoutMigrationBackup) && !Folder::delete($this->layoutMigrationBackup)) {
                        $this->app->enqueueMessage('Dharma Shop: снимок переноса остался в ' . $this->layoutMigrationBackup, 'warning');
                    }
                }

                /** Ссылки и специальные файлы не должны теряться при переносе. */
                private function layoutFiles(string $directory): array
                {
                    if (is_link($directory)) {
                        throw new \RuntimeException('Перенос макетов остановлен: каталог является ссылкой: ' . $directory);
                    }

                    $files = [];
                    $iterator = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
                        \RecursiveIteratorIterator::SELF_FIRST,
                    );
                    foreach ($iterator as $entry) {
                        if ($entry->isLink() || (!$entry->isDir() && !$entry->isFile())) {
                            throw new \RuntimeException('Перенос макетов остановлен: неподдерживаемый файл: ' . $entry->getPathname());
                        }
                        if ($entry->isFile()) {
                            $files[] = substr($entry->getPathname(), strlen(rtrim($directory, '/\\')) + 1);
                        }
                    }

                    return $files;
                }

                private function copyLayoutFiles(string $source, string $target, array $files, bool $overwrite = true): void
                {
                    foreach ($files as $relative) {
                        $destination = $target . '/' . $relative;
                        if (!$overwrite && file_exists($destination)) {
                            continue;
                        }
                        if (!Folder::create(dirname($destination)) || !File::copy($source . '/' . $relative, $destination)) {
                            throw new \RuntimeException('Не удалось перенести макет: ' . $relative . '. Снимок: ' . $this->layoutMigrationBackup);
                        }
                    }
                }

                /**
                 * Три бейджа магазина при первой установке: «Хит», «Новинка», «Акция». Бейдж «Акция» запоминается в настройках
                 * (`sale_badge_id`) для автоматической установки товарам со скидкой. При обновлении не вызывается, чтобы не вернуть удалённое.
                 */
                private function ensureDefaultBadges(): void
                {
                    $extension = 'com_dharmashop.badge';
                    $exists = (int) $this->db->setQuery(
                        $this->db->getQuery(true)->select('COUNT(*)')->from($this->db->quoteName('#__categories'))
                            ->where($this->db->quoteName('extension') . ' = ' . $this->db->quote($extension))
                    )->loadResult();

                    if ($exists > 0) {
                        return;
                    }

                    $saleId = 0;

                    foreach ([['Хит', 'khit', 'danger'], ['Новинка', 'novinka', 'success'], ['Акция', 'akciya', 'warning']] as [$title, $alias, $color]) {
                        $table = new \Joomla\CMS\Table\Category($this->db);
                        $table->setLocation(1, 'last-child');
                        $data = [
                            'title' => $title,
                            'alias' => $alias,
                            'extension' => $extension,
                            'published' => 1,
                            'access' => 1,
                            'language' => '*',
                            'description' => '',
                            'params' => json_encode(['badge_color' => $color, 'badge_show_list' => 1]),
                            'metadata' => '{}',
                            'created_user_id' => 0,
                        ];

                        if (!$table->bind($data) || !$table->check() || !$table->store()) {
                            $this->app->enqueueMessage('Не удалось создать бейдж «' . $title . '»: ' . $table->getError(), 'warning');

                            continue;
                        }

                        $table->rebuildPath($table->id);

                        if ($alias === 'akciya') {
                            $saleId = (int) $table->id;
                        }
                    }

                    if ($saleId > 0) {
                        $query = $this->db->getQuery(true)->select($this->db->quoteName('params'))->from($this->db->quoteName('#__extensions'))
                            ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('component'))
                            ->where($this->db->quoteName('element') . ' = ' . $this->db->quote('com_dharmashop'));
                        $params = json_decode((string) $this->db->setQuery($query)->loadResult(), true) ?: [];
                        $params['sale_badge_id'] = $saleId;
                        $params += ['auto_sale_badge' => 1];
                        $update = $this->db->getQuery(true)->update($this->db->quoteName('#__extensions'))
                            ->set($this->db->quoteName('params') . ' = ' . $this->db->quote(json_encode($params)))
                            ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('component'))
                            ->where($this->db->quoteName('element') . ' = ' . $this->db->quote('com_dharmashop'));
                        $this->db->setQuery($update)->execute();
                    }
                }

                /** Создаёт категорию «Без категории», если у компонента нет ни одной категории (товар нельзя сохранить без категории). */
                private function ensureDefaultCategory(): void
                {
                    $count = (int) $this->db->setQuery(
                        $this->db->getQuery(true)->select('COUNT(*)')->from($this->db->quoteName('#__categories'))
                            ->where($this->db->quoteName('extension') . ' = ' . $this->db->quote('com_dharmashop'))
                    )->loadResult();

                    if ($count > 0) {
                        return;
                    }

                    $table = new \Joomla\CMS\Table\Category($this->db);
                    $table->setLocation(1, 'last-child');
                    $data = [
                        'title' => 'Без категории',
                        'alias' => 'uncategorised',
                        'extension' => 'com_dharmashop',
                        'published' => 1,
                        'access' => 1,
                        'language' => '*',
                        'description' => '',
                        'params' => '{}',
                        'metadata' => '{}',
                        'created_user_id' => 0,
                    ];

                    if (!$table->bind($data) || !$table->check() || !$table->store()) {
                        $this->app->enqueueMessage('Не удалось создать категорию по умолчанию: ' . $table->getError(), 'warning');

                        return;
                    }

                    $table->rebuildPath($table->id);
                }
            }
        );
    }
};
