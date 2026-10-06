<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

\defined('_JEXEC') or die;

use Dharma\Component\DharmaShop\Administrator\Extension\DharmaShopComponent;
use Joomla\CMS\Categories\CategoryFactoryInterface;
use Joomla\CMS\Component\Router\RouterFactoryInterface;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\CategoryFactory;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Extension\Service\Provider\RouterFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->registerServiceProvider(new CategoryFactory('\\Dharma\\Component\\DharmaShop'));
        $container->registerServiceProvider(new MVCFactory('\\Dharma\\Component\\DharmaShop'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('\\Dharma\\Component\\DharmaShop'));
        $container->registerServiceProvider(new RouterFactory('\\Dharma\\Component\\DharmaShop'));

        $container->set(
            ComponentInterface::class,
            static function (Container $c): DharmaShopComponent {
                $component = new DharmaShopComponent($c->get(ComponentDispatcherFactoryInterface::class));
                $component->setMVCFactory($c->get(MVCFactoryInterface::class));
                $component->setCategoryFactory($c->get(CategoryFactoryInterface::class));
                $component->setRouterFactory($c->get(RouterFactoryInterface::class));

                return $component;
            }
        );
    }
};
