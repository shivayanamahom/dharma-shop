<?php
declare(strict_types=1);
namespace Dharma\Plugin\DharmaShop\ImageResize\Image;
use Joomla\CMS\Image\Image;
use Joomla\CMS\Uri\Uri;
\defined('_JEXEC') or die;

/** Local raster derivatives only. Originals are never overwritten. */
final class Resizer
{
    public function resolve(string $url, string $context, array $preset, string $cache): string
    {
        if (!extension_loaded('gd')) return $url;
        $root = parse_url(Uri::root()); $source = parse_url($url);
        if (!$root || !$source || strtolower($root['host'] ?? '') !== strtolower($source['host'] ?? '')
            || ($root['port'] ?? null) !== ($source['port'] ?? null)) return $url;
        $prefix = rtrim(rawurldecode($root['path'] ?? ''), '/') . '/';
        $path = rawurldecode($source['path'] ?? '');
        if (!str_starts_with($path, $prefix) || str_contains($path, "\0")) return $url;
        $site = realpath(JPATH_ROOT);
        $file = realpath(JPATH_ROOT . '/' . substr($path, strlen($prefix)));
        if (!$site || !$file || !str_starts_with($file, $site . DIRECTORY_SEPARATOR) || !is_file($file) || !is_readable($file)
            || filesize($file) > 32 * 1024 * 1024) return $url;
        $info = @getimagesize($file);
        $types = ['image/jpeg'=>'jpeg','image/png'=>'png','image/webp'=>'webp','image/avif'=>'avif'];
        if (!$info || !isset($types[$info['mime'] ?? ''])) return $url; // SVG and animated GIF pass through.
        // Bound decoded memory before GD allocates the source and destination bitmaps.
        $pixels = (int)$info[0] * (int)$info[1];
        if ($pixels > 24000000) return $url;
        $limit = $this->memoryLimit();
        if ($limit > 0 && memory_get_usage(true) + $pixels * 12 + 48 * 1024 * 1024 > $limit) return $url;
        $format = $preset['format'] === 'original' ? $types[$info['mime']] : $preset['format'];
        if ($format === 'webp' && !function_exists('imagewebp')) $format = 'png';
        if ($format === 'avif' && !function_exists('imageavif')) $format = 'png';
        $outputType = ['jpeg'=>IMAGETYPE_JPEG,'png'=>IMAGETYPE_PNG,'webp'=>IMAGETYPE_WEBP,'avif'=>IMAGETYPE_AVIF][$format];
        // Only effective rendering parameters identify a copy; context names and format aliases do not.
        // PNG is lossless, so changing the shared JPEG/WebP quality must not duplicate PNG copies.
        $preset = [
            'width' => (int) $preset['width'],
            'height' => (int) $preset['height'],
            'mode' => (string) $preset['mode'],
            'format' => $format,
            'quality' => $format === 'png' ? 9 : (int) $preset['quality'],
        ];
        $sourceKey = hash('sha256', $file);
        $variant = hash('sha256', json_encode(['v2',filemtime($file),filesize($file),$preset], JSON_THROW_ON_ERROR));
        if (!preg_match('~^images/(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9_-]+$~D', $cache)) return $url;
        // One shared store, sharded by source. Identical presets share the URL, file and lock across contexts.
        // Keep the context argument for caller compatibility; it is only used upstream to select settings.
        $relative = $cache . '/v2/' . substr($sourceKey,0,2) . '/' . substr($sourceKey,2,2);
        $folder = $this->folder($site, $relative);
        if ($folder === null) return $url;
        $size = ($preset['width'] ?: 'auto') . 'x' . ($preset['height'] ?: 'auto');
        $qualityLabel = $format === 'png' ? 'lossless' : 'q' . $preset['quality'];
        $name = $size . '-' . $preset['mode'] . '-' . $qualityLabel . '-'
            . substr($sourceKey,0,32) . '-' . substr($variant,0,24) . '.' . ($format === 'jpeg' ? 'jpg' : $format);
        $target = $folder . '/' . $name;
        $resultUrl = rtrim(Uri::root(), '/') . '/' . $relative . '/' . $name;
        if (is_file($target) && !is_link($target) && filesize($target) > 0) return $resultUrl;
        $lockPath = $target . '.lock';
        if (is_link($lockPath)) return $url;
        $lock = @fopen($lockPath, 'c');
        if (!$lock) return $url;
        $temporary = null; $image = null; $resized = null;
        try {
            if (!flock($lock, LOCK_EX)) return $url;
            clearstatcache(true, $target);
            if (is_file($target) && !is_link($target) && filesize($target) > 0) return $resultUrl;
            $width = $preset['width']; $height = $preset['height'];
            if (!$width) $width = max(1,(int)round($height * $info[0] / $info[1]));
            if (!$height) $height = max(1,(int)round($width * $info[1] / $info[0]));
            $bound = min(1.0, 2400 / $width, 2400 / $height);
            $width=max(1,(int)floor($width*$bound)); $height=max(1,(int)floor($height*$bound));
            // Never enlarge a small original. Keep the desired aspect ratio for cover/stretch.
            $scale = min(1.0, $info[0] / $width, $info[1] / $height);
            if ($preset['mode'] !== 'contain') { $width=max(1,(int)floor($width*$scale)); $height=max(1,(int)floor($height*$scale)); }
            else { $width=min($width,(int)$info[0]); $height=min($height,(int)$info[1]); }
            $image = new Image($file);
            $resized = match ($preset['mode']) {
                'cover' => $image->cropResize($width,$height),
                'stretch' => $image->resize($width,$height,true,Image::SCALE_FILL),
                default => $image->resize($width,$height,true,Image::SCALE_INSIDE),
            };
            if ($format === 'jpeg') {
                // JPEG cannot retain alpha: use a white background for transparent products/logos.
                $canvas = imagecreatetruecolor($resized->getWidth(),$resized->getHeight());
                imagefill($canvas,0,0,imagecolorallocate($canvas,255,255,255));
                imagecopy($canvas,$resized->getHandle(),0,0,0,0,$resized->getWidth(),$resized->getHeight());
                $resized->destroy(); $resized = new Image($canvas);
            }
            $temporary = tempnam($folder, '.image-');
            if ($temporary === false) return $url;
            $quality = $format === 'png' ? 9 : $preset['quality'];
            if (!$resized->toFile($temporary,$outputType,['quality'=>$quality])) return $url;
            $check = @getimagesize($temporary);
            if (!$check || (int)$check[2] !== $outputType || $check[0] !== $resized->getWidth() || $check[1] !== $resized->getHeight()) return $url;
            @chmod($temporary,0644);
            if (!@rename($temporary,$target)) return $url;
            $temporary = null;
            return $resultUrl;
        } finally {
            if (is_string($temporary) && is_file($temporary)) @unlink($temporary);
            $resized?->destroy(); $image?->destroy();
            flock($lock,LOCK_UN); fclose($lock);
        }
    }

    /** Validate each existing ancestor before creating children; never follow a symlink outside the site. */
    private function folder(string $site,string $relative): ?string
    {
        $folder = $site;
        foreach (explode('/',$relative) as $part) {
            $folder .= '/' . $part;
            if (!is_dir($folder) && !@mkdir($folder,0755) && !is_dir($folder)) return null;
            $real = realpath($folder);
            if (!$real || !str_starts_with($real,$site . DIRECTORY_SEPARATOR)) return null;
            $folder = $real;
        }
        return $folder;
    }
    private function memoryLimit(): int
    {
        $value = trim((string)ini_get('memory_limit'));
        if ($value === '' || $value === '-1') return 0;
        return (int)$value * match (strtolower(substr($value,-1))) { 'g'=>1073741824,'m'=>1048576,'k'=>1024,default=>1 };
    }
}
