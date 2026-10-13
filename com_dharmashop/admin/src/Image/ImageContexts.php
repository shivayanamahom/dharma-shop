<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Image;
use Joomla\Registry\Registry;
\defined('_JEXEC') or die;

/** Stable public IDs shared by layouts, native settings and the image plugin. */
final class ImageContexts
{
    public const SIZES = [
        'product.category.grid' => [600, 600],
        'product.category.list' => [320, 320],
        'product.category.table' => [112, 112],
        'product.related.grid' => [600, 600],
        'product.related.list' => [320, 320],
        'product.main' => [1000, 1000],
        'product.thumb' => [160, 160],
        'product.lightbox' => [1800, 1800],
        'category.card' => [600, 400],
        'category.header' => [1200, 600],
        'brand.card' => [600, 400],
        'brand.header' => [1200, 600],
        'landing.header' => [1200, 600],
        'landing.card' => [600, 400],
        'badge.card' => [600, 400],
        'badge.header' => [1200, 600],
        'shipping.option' => [96, 96],
        'payment.option' => [96, 96],
        'cart.module' => [144, 144],
        'cart.page' => [192, 192],
        'cart.toast' => [128, 128],
        'search.module' => [112, 112],
        'search.grid' => [600, 600],
        'search.list' => [320, 320],
        'search.table' => [112, 112],
        'module.products.grid' => [600, 600],
        'module.products.list' => [240, 240],
        'module.products.table' => [112, 112],
        'module.products.slider' => [600, 600],
        'favorites.item.grid' => [600, 600],
        'favorites.item.list' => [320, 320],
        'favorites.item.table' => [112, 112],
        'module.categories.grid' => [600, 400],
        'module.categories.accordion' => [600, 400],
        'module.categories.slider' => [600, 400],
        'group.choice' => [96, 96],
        'group.teaser.grid' => [600, 600],
        'group.teaser.list' => [480, 480],
        'group.teaser.table' => [112, 112],
        'field.option' => [96, 96],
        'field.option.filter' => [96, 96],
        'field.color.admin' => [96, 96],
        'field.color.filter' => [96, 96],
        'field.color.card' => [96, 96],
        'field.color.page' => [96, 96],
        'field.gallery.card' => [240, 180],
        'field.gallery.page' => [600, 450],
        'field.gallery.lightbox' => [1800, 1800],
        'field.video.poster' => [640, 360],
        'admin.product' => [96, 96],
        'admin.group' => [96, 96],
        'admin.picker.field' => [128, 128],
        'admin.picker.member' => [128, 128],
        'admin.picker.member.choice' => [64, 64],
        'admin.picker.modal' => [96, 96],
        'admin.picker.group' => [96, 96],
        'admin.category' => [96, 96],
        'email.product' => [128, 128],
        'email.logo' => [320, 160],
        'seo.social' => [1200, 630],
    ];
    public static function label(string $context): string
    {
        return 'COM_DHARMASHOP_IMAGE_CONTEXT_' . strtoupper(str_replace('.', '_', $context));
    }
    public static function defaults(): array
    {
        $rows = [];
        foreach (self::SIZES as $context => [$width, $height]) {
            $rows[] = ['context'=>$context, 'width'=>$width, 'height'=>$height, 'mode'=>'contain',
                'format'=>str_starts_with($context, 'email.') ? 'png' : 'auto'];
        }
        return $rows;
    }

    /** Contexts introduced after the settings were saved (landing, related products, social image…) work with older saved presets: a missing row means the default size. Explicit rows, including 0 × 0 (original), win. */
    public static function categoryModuleDefaults(mixed $rows): array
    {
        if (is_string($rows)) { $rows = json_decode($rows, true) ?: []; }
        $rows = (array) $rows;
        $configured = [];
        foreach ($rows as $row) { $configured[(string) (((array) $row)['context'] ?? '')] = true; }
        foreach (self::defaults() as $row) {
            if (!isset($configured[$row['context']])) {
                $rows[] = $row;
            }
        }
        return $rows;
    }
    public static function presets(Registry $settings): array
    {
        $rows = $settings->get('image_resize_presets');
        if ($rows === null || $rows === '') $rows = self::defaults();
        if (is_string($rows)) $rows = json_decode($rows, true) ?: [];
        $rows = self::categoryModuleDefaults($rows);
        $result = [];
        $quality = ImageQuality::resolve($settings);
        foreach ((array) $rows as $row) {
            $row = (array) $row;
            $context = (string) ($row['context'] ?? '');
            if (!isset(self::SIZES[$context]) || isset($result[$context])) continue;
            $width = max(0, min(2400, (int) ($row['width'] ?? 0)));
            $height = max(0, min(2400, (int) ($row['height'] ?? 0)));
            if (!$width && !$height) continue;
            $mode = (string) ($row['mode'] ?? 'contain');
            if (!in_array($mode, ['contain','cover','stretch'], true) || (!$width || !$height)) $mode = 'contain';
            $format = (string) ($row['format'] ?? 'auto');
            if (!in_array($format, ['auto','webp','jpeg','png','original'], true)) $format = 'auto';
            // Mail clients receive a common raster format, even after an accidental WebP selection.
            if ($format === 'auto') $format = (int)$settings->get('image_convert_webp',0) === 1 ? 'webp' : 'original';
            if (str_starts_with($context, 'email.') && !in_array($format, ['jpeg','png'], true)) $format = 'png';
            $result[$context] = ['width'=>$width,'height'=>$height,'mode'=>$mode,'format'=>$format,
                'quality'=>$quality];
        }
        return $result;
    }
}
