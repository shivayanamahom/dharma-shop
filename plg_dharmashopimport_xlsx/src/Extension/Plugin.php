<?php
declare(strict_types=1);
namespace Dharma\Plugin\DharmaShopImport\Xlsx\Extension;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
\defined('_JEXEC') or die;
final class Plugin extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;
    public static function getSubscribedEvents(): array { return ['onDharmaShopImportReadersCollect' => 'collect']; }
    public function collect(Event $event): void
    {
        $readers = $event->getArgument('readers', []);
        $readers['xlsx'] = new \Dharma\Plugin\DharmaShopImport\Xlsx\Reader();
        $event->setArgument('readers', $readers);
    }
}
