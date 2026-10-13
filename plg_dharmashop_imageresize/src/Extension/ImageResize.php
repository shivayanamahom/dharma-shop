<?php
declare(strict_types=1);
namespace Dharma\Plugin\DharmaShop\ImageResize\Extension;
use Dharma\Component\DharmaShop\Administrator\Image\ImageContexts;
use Dharma\Component\DharmaShop\Administrator\Image\ImageRequest;
use Dharma\Plugin\DharmaShop\ImageResize\Image\Resizer;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;
\defined('_JEXEC') or die;
final class ImageResize extends CMSPlugin implements SubscriberInterface
{
    private ?array $presets = null;
    public static function getSubscribedEvents(): array { return ['onDharmaShopImagePrepare'=>'prepare']; }
    public function prepare(EventInterface $event): void
    {
        $request = $event->getArgument('request'); $settings = $event->getArgument('settings');
        if (!$request instanceof ImageRequest || !(int)$settings->get('image_resize_enabled',1)) return;
        $this->presets ??= ImageContexts::presets($settings);
        $preset = $this->presets[$request->context] ?? null;
        $resizer = new Resizer();
        if ($preset === null) { [$request->width, $request->height] = $resizer->dimensions($request->url); return; }
        $request->url = $resizer->resolve($request->source, $request->context, $preset, (string)$settings->get('image_resize_cache','images/dharmashop/cache/resized'));
        [$request->width, $request->height] = $resizer->dimensions($request->url);
    }
}
