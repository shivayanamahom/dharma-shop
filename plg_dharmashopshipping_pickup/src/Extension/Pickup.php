<?php
declare(strict_types=1);

namespace Dharma\Plugin\DharmaShopShipping\Pickup\Extension;

use Dharma\Component\DharmaShop\Administrator\Shipping\TypeRegistry;
use Dharma\Plugin\DharmaShopShipping\Pickup\Type\PickupType;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;

\defined('_JEXEC') or die;

/** Регистрирует тип «Самовывоз» в реестре типов доставки Dharma Shop. */
final class Pickup extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    protected $allowLegacyListeners = false;

    public static function getSubscribedEvents(): array
    {
        return ['onDharmaShopShippingTypesCollect' => 'registerTypes'];
    }

    public function registerTypes(EventInterface $event): void
    {
        $registry = $event->getArgument('registry');

        if ($registry instanceof TypeRegistry) {
            $registry->register(new PickupType());
        }
    }
}
