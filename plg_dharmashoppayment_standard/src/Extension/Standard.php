<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    plg_dharmashoppayment_standard
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Plugin\DharmaShopPayment\Standard\Extension;

use Dharma\Component\DharmaShop\Administrator\Payment\TypeRegistry;
use Dharma\Plugin\DharmaShopPayment\Standard\Type\StandardType;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;

\defined('_JEXEC') or die;

final class Standard extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;
    protected $allowLegacyListeners = false;

    public static function getSubscribedEvents(): array
    {
        return ['onDharmaShopPaymentTypesCollect' => 'registerTypes'];
    }

    public function registerTypes(EventInterface $event): void
    {
        $registry = $event->getArgument('registry');

        if ($registry instanceof TypeRegistry) {
            $registry->register(new StandardType());
        }
    }
}
