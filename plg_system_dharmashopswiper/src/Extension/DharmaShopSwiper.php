<?php
declare(strict_types=1);

namespace Dharma\Plugin\System\DharmaShopSwiper\Extension;

use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Document\JsonDocument;
use Joomla\CMS\Document\RawDocument;
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
        $input = $app->getInput();
        $format = $input->getCmd('format', 'html');
        $groupCard = $format === 'json' && $input->getCmd('option') === 'com_dharmashop'
            && $input->getCmd('task') === 'groupcard.select';

        // Реестр нужен и на HTML-страницах, и в HTML-фрагментах каталога и в HTML карточки внутри JSON.
        if (!$app->isClient('site') || (!$groupCard && !in_array($format, ['html', 'raw'], true))) {
            return;
        }

        $doc = Factory::getDocument();

        if (!$doc instanceof HtmlDocument && !$doc instanceof RawDocument && !($groupCard && $doc instanceof JsonDocument)) {
            return;
        }

        $doc->getWebAssetManager()->getRegistry()->addRegistryFile('media/plg_system_dharmashopswiper/joomla.asset.json');
    }
}
