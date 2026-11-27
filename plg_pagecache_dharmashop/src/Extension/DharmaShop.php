<?php
declare(strict_types=1);

namespace Dharma\Plugin\PageCache\DharmaShop\Extension;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\PageCache\GetKeyEvent;
use Joomla\CMS\Event\PageCache\SetCachingEvent;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

\defined('_JEXEC') or die;

final class DharmaShop extends CMSPlugin implements SubscriberInterface
{
    protected $allowLegacyListeners = false;
    private bool $bypass = false;

    public static function getSubscribedEvents(): array
    {
        return [
            'onPageCacheSetCaching' => 'setCaching',
            'onPageCacheGetKey' => 'getCacheKey',
        ];
    }

    public function setCaching(SetCachingEvent $event): void
    {
        $option = $this->getApplication()->getInput()->getCmd('option');
        $this->bypass = in_array($option, ['com_dharmashop', 'com_dharmashop_landing'], true)
            || ModuleHelper::isEnabled('mod_dharmashop_favorites')
            || ModuleHelper::isEnabled('mod_dharmashop_products')
            || (ModuleHelper::isEnabled('mod_dharmashop_cart')
                && (int) ComponentHelper::getParams('com_dharmashop')->get('catalog_mode', 0) !== 1);

        if ($this->bypass) {
            $event->addResult(false);
        }
    }

    public function getCacheKey(GetKeyEvent $event): void
    {
        // PageController проверяет ETag до чтения кэша, даже когда кэширование выключено.
        if ($this->bypass) {
            $event->addResult('dharmashop-pagecache-bypass');
        }
    }
}
