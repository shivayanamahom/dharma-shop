<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Site\Support;

use Joomla\CMS\Factory;

\defined('_JEXEC') or die;

/** Resources and instance data for additional field galleries, independent of the product gallery. */
final class FieldGallery
{
    public static function prepare(object $field): array
    {
        $settings = (array) ($field->gallery_settings ?? []);
        $wa = Factory::getDocument()->getWebAssetManager();
        $registry = $wa->getRegistry();
        $registry->addExtensionRegistryFile('com_dharmashop');
        $slider = ($settings['gallery_layout'] ?? 'grid') === 'slider'
            && $registry->exists('preset', 'dharmashop.swiper');
        $fancybox = $registry->exists('preset', 'dharmashop.fancybox') ? 'dharmashop.fancybox'
            : ($registry->exists('preset', 'wtfancybox') ? 'wtfancybox' : '');
        $lightbox = (int) ($settings['gallery_lightbox'] ?? 1) === 1 && $fancybox !== '';
        $presets = array_filter([$slider ? 'dharmashop.swiper' : '', $lightbox ? $fancybox : '']);
        if ($presets) {
            // Merge dependencies across instances: a later grid must not discard the first slider's dependency.
            $asset = $wa->getAsset('script', 'com_dharmashop.field-gallery');
            $dependencies = $asset->getDependencies();
            foreach ($presets as $preset) {
                $wa->usePreset($preset);
                foreach ($wa->getAsset('preset', $preset)->getDependencies() as $dependency) {
                    $parts = explode('#', $dependency, 2);
                    if (($parts[1] ?? 'script') === 'script') { $dependencies[] = $parts[0]; }
                }
            }
            $wa->registerScript($asset->getName(), $asset->getUri(false), ['version'=>$asset->getVersion()],
                ['defer'=>true], array_values(array_unique($dependencies)))->useScript($asset->getName());
        }
        $columns = [];
        foreach (['xs'=>1, 'sm'=>1, 'md'=>2, 'lg'=>2, 'xl'=>3, 'xxl'=>3] as $breakpoint => $default) {
            $columns[$breakpoint] = max(1, min(6, (int) ($settings['gallery_columns_' . $breakpoint] ?? $default)));
        }
        return [
            'id'=>'dharmaFieldGallery-' . (int) $field->id . '-' . bin2hex(random_bytes(5)),
            'layout'=>$slider ? 'slider' : 'grid', 'columns'=>$columns,
            'lightbox'=>$lightbox, 'caption'=>(int) ($settings['gallery_caption'] ?? 1) === 1,
        ];
    }
}
