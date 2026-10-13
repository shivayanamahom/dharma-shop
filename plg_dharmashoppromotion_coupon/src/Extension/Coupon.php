<?php
declare(strict_types=1);
namespace Dharma\Plugin\DharmaShopPromotion\Coupon\Extension;
use Dharma\Component\DharmaShop\Administrator\Promotion\TypeRegistry;
use Dharma\Plugin\DharmaShopPromotion\Coupon\Type\CouponType;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;
\defined('_JEXEC') or die;
final class Coupon extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;
    protected $allowLegacyListeners = false;
    public static function getSubscribedEvents(): array { return ['onDharmaShopPromotionTypesCollect'=>'registerTypes']; }
    public function registerTypes(EventInterface $event): void
    {
        $registry = $event->getArgument('registry');
        if ($registry instanceof TypeRegistry) $registry->register(new CouponType());
    }
}
