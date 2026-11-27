<?php
declare(strict_types=1);

namespace Dharma\Plugin\System\DharmaShopFancybox\Extension;

use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Document\JsonDocument;
use Joomla\CMS\Document\RawDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

\defined('_JEXEC') or die;

/**
 * Регистрирует библиотеку Fancybox (Fancyapps UI) в Web Asset Manager сайта: скрипт и стиль `dharmashop.fancybox` и набор `dharmashop.fancybox`.
 * Лицензия Fancybox — Fancyapps UI License Agreement (см. media/…/LICENSE-fancybox.md), не MIT/GPL: коммерческое использование — по лицензии владельца сайта.
 * Файлы лежат в media/plg_system_dharmashopfancybox; подключает их только тот, кому они нужны (`$wa->usePreset('dharmashop.fancybox')`).
 */
final class DharmaShopFancybox extends CMSPlugin implements SubscriberInterface
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

        $doc->getWebAssetManager()->getRegistry()->addRegistryFile('media/plg_system_dharmashopfancybox/joomla.asset.json');
    }
}
