<?php
declare(strict_types=1);

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(InstallerScriptInterface::class, new class implements InstallerScriptInterface {
            public function preflight(string $type, InstallerAdapter $adapter): bool { return true; }
            public function install(InstallerAdapter $adapter): bool { return true; }
            public function update(InstallerAdapter $adapter): bool { return true; }
            public function uninstall(InstallerAdapter $adapter): bool
            {
                Factory::getContainer()->get('DatabaseDriver')->dropTable('#__dharmashop_related_products', true);
                return true;
            }
            public function postflight(string $type, InstallerAdapter $adapter): bool
            {
                if ($type === 'uninstall') { return true; }
                $db = Factory::getContainer()->get('DatabaseDriver');
                $tables = $db->getTableList();
                $new = !in_array($db->replacePrefix('#__dharmashop_related_products'), $tables, true);
                $root = JPATH_PLUGINS . '/system/dharmashoprelated';
                $db->setQuery(file_get_contents($root . '/sql/install.mysql.utf8.sql'))->execute();
                // При первой установке пакета компонент может устанавливаться после плагина.
                if ($new && in_array($db->replacePrefix('#__dharmashop_products'), $tables, true)) {
                    require_once $root . '/src/Support/Blocks.php';
                    require_once $root . '/src/Support/Relations.php';
                    (new \Dharma\Plugin\System\DharmaShopRelated\Support\Relations($db))->rebuild();
                }
                return true;
            }
        });
    }
};
