<?php
declare(strict_types=1);
namespace Dharma\Plugin\DharmaShopField\Basic\Extension;
use Dharma\Component\DharmaShop\Administrator\Fields\TypeRegistry;
use Dharma\Plugin\DharmaShopField\Basic\Type\{CheckboxType, ListType, TextType, EditorType, NumberType, RangeType, FilesType, GalleryType, VideoType};
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;
\defined('_JEXEC') or die;
final class Basic extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;
    public static function getSubscribedEvents(): array { return ['onDharmaShopFieldTypesCollect' => 'registerTypes']; }
    public function registerTypes(EventInterface $event): void
    {
        $registry = $event->getArgument('registry');
        if (!$registry instanceof TypeRegistry) { return; }
        foreach ([new CheckboxType(), new ListType(), new TextType(), new EditorType(), new NumberType(), new RangeType(), new FilesType(), new GalleryType(), new VideoType()] as $type) {
            $registry->register($type);
        }
    }
}
