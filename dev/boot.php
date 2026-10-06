<?php
// Dev-only: поднимает консольное приложение Joomla на тестовой копии сайта.
// Путь к сайту: переменная окружения DHARMA_SITE (по умолчанию /Volumes/Work/Server/www/test).
// Использование: require __DIR__ . '/boot.php'; затем Factory::getApplication().
const _JEXEC = 1;
$site = rtrim(getenv('DHARMA_SITE') ?: '/Volumes/Work/Server/www/test', '/');
// Как ConsoleApplication::populateHttpHost(): у консоли нет запроса, подставляем служебные значения.
$_SERVER['HTTP_HOST'] = 'joomla.invalid'; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['HTTPS'] = 'on';
define('JPATH_BASE', $site);
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';
$container = \Joomla\CMS\Factory::getContainer();
$container->alias('session', 'session.cli')
    ->alias('JSession', 'session.cli')
    ->alias(\Joomla\CMS\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\SessionInterface::class, 'session.cli');
$app = $container->get(\Joomla\Console\Application::class);
\Joomla\CMS\Factory::$application = $app;
if (method_exists($app, 'createExtensionNamespaceMap')) { $app->createExtensionNamespaceMap(); }
