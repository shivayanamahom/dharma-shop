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

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            InstallerScriptInterface::class,
            new class (Factory::getApplication(), $container->get(DatabaseInterface::class)) implements InstallerScriptInterface {
                private const MIN_JOOMLA = '6.0.0';

                private const MIN_PHP = '8.3.0';

                /** Плагины пакета, которые включаем сразу после установки: [группа, элемент]. */
                private const PLUGINS = [];

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

                    if (version_compare(PHP_VERSION, self::MIN_PHP, '<')) {
                        $this->app->enqueueMessage(sprintf('Dharma Shop требует PHP %s или новее.', self::MIN_PHP), 'error');

                        return false;
                    }

                    if (version_compare(JVERSION, self::MIN_JOOMLA, '<')) {
                        $this->app->enqueueMessage(sprintf('Dharma Shop требует Joomla %s или новее.', self::MIN_JOOMLA), 'error');

                        return false;
                    }

                    return true;
                }

                public function postflight(string $type, InstallerAdapter $adapter): bool
                {
                    if ($type === 'uninstall') {
                        return true;
                    }

                    foreach (self::PLUGINS as [$group, $element]) {
                        $query = $this->db->getQuery(true)
                            ->update($this->db->quoteName('#__extensions'))
                            ->set($this->db->quoteName('enabled') . ' = 1')
                            ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('plugin'))
                            ->where($this->db->quoteName('folder') . ' = ' . $this->db->quote($group))
                            ->where($this->db->quoteName('element') . ' = ' . $this->db->quote($element));

                        $this->db->setQuery($query)->execute();
                    }

                    $this->ensureDefaultCategory();

                    if ($type === 'install') {
                        $this->app->enqueueMessage(Text::_('PKG_DHARMASHOP_INSTALLED'), 'info');
                    }

                    return true;
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
