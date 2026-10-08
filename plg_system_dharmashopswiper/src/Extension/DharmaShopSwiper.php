<?php
declare(strict_types=1);

namespace Dharma\Plugin\System\DharmaShopSwiper\Extension;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

\defined('_JEXEC') or die;

/**
 * Регистрирует библиотеку Swiper (MIT) в Web Asset Manager сайта: скрипт и стиль `dharmashop.swiper` и набор `dharmashop.swiper`.
 * Файлы лежат в media/plg_system_dharmashopswiper; подключает их только тот, кому они нужны (`$wa->usePreset('dharmashop.swiper')`).
 */
final class DharmaShopSwiper extends CMSPlugin implements SubscriberInterface
{
    protected $allowLegacyListeners = false;

    public static function getSubscribedEvents(): array
    {
        return ['onAfterRoute' => 'registerAssets'];
    }

    public function registerAssets(): void
    {
        $app = $this->getApplication();

        if (!$app->isClient('site')) {
            return;
        }

        Factory::getDocument()->getWebAssetManager()->getRegistry()->addRegistryFile('media/plg_system_dharmashopswiper/joomla.asset.json');
    }
}
