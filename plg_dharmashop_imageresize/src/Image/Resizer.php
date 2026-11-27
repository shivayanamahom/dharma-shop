<?php
declare(strict_types=1);
namespace Dharma\Plugin\DharmaShop\ImageResize\Image;
use Joomla\CMS\Image\Image;
use Joomla\CMS\Uri\Uri;
\defined('_JEXEC') or die;

/** Local raster derivatives only. Originals are never overwritten. */
final class Resizer
{
    private const IMAGE_TYPES = ['image/jpeg' => 'jpeg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/avif' => 'avif'];

    private ?string $siteRoot = null;
    /** @var array{host: string, port: ?int, prefix: string, root: string}|null */
    private ?array $rootInfo = null;
    private ?int $memLimit = null;
    /** @var array<string, ?string> */
    private array $folderCache = [];
    /** @var array<string, array{size: int, mtime: int, info: ?array}> */
    private array $sourceCache = [];
    /** @var array<string, array{int, int}> */
    private array $dimensionsCache = [];
    /** @var array<string, array{string, int, int}> */
    private array $resolvedCache = [];

    public function resolve(string $url, string $context, array $preset, string $cache): string
    {
        return $this->resolveWithDimensions($url, $context, $preset, $cache)[0];
    }

    /**
     * Создаёт/находит локальную копию изображения по пресету и сразу возвращает `[url, width, height]`
     * без повторного разбора URI и повторного `realpath()` при расчёте размеров.
     *
     * @return  array{0: string, 1: int, 2: int}
     */
    public function resolveWithDimensions(string $url, string $context, array $preset, string $cache): array
    {
        if (!extension_loaded('gd')) {
            return [$url, ...$this->dimensions($url)];
        }

        $cacheKey = $url . "\0" . $cache . "\0" . ($preset['width'] ?? 0) . 'x' . ($preset['height'] ?? 0) . ':' . ($preset['mode'] ?? '') . ':' . ($preset['format'] ?? '') . ':' . ($preset['quality'] ?? 0);

        if (isset($this->resolvedCache[$cacheKey])) {
            return $this->resolvedCache[$cacheKey];
        }

        $file = $this->localPath($url);

        if ($file === null || !preg_match('~^images/(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9_-]+$~D', $cache)) {
            return $this->resolvedCache[$cacheKey] = [$url, ...$this->fileDimensions($file)];
        }

        $meta = $this->sourceMeta($file);

        if ($meta === null || $meta['size'] > 32 * 1024 * 1024 || $meta['info'] === null) {
            return $this->resolvedCache[$cacheKey] = [$url, ...$this->fileDimensions($file)];
        }

        $info = $meta['info'];
        $origDims = ((int) $info[0] > 0 && (int) $info[1] > 0) ? [(int) $info[0], (int) $info[1]] : [0, 0];

        if (!isset(self::IMAGE_TYPES[$info['mime'] ?? ''])) {
            return $this->resolvedCache[$cacheKey] = [$url, ...$origDims]; // SVG and animated GIF pass through.
        }

        // Bound decoded memory before GD allocates the source and destination bitmaps.
        $pixels = (int) $info[0] * (int) $info[1];
        $limit = $this->memoryLimit();

        if ($pixels > 24000000 || ($limit > 0 && memory_get_usage(true) + $pixels * 12 + 48 * 1024 * 1024 > $limit)) {
            return $this->resolvedCache[$cacheKey] = [$url, ...$origDims];
        }

        $format = $preset['format'] === 'original' ? self::IMAGE_TYPES[$info['mime']] : $preset['format'];
        if ($format === 'webp' && !function_exists('imagewebp')) $format = 'png';
        if ($format === 'avif' && !function_exists('imageavif')) $format = 'png';
        $outputType = ['jpeg' => IMAGETYPE_JPEG, 'png' => IMAGETYPE_PNG, 'webp' => IMAGETYPE_WEBP, 'avif' => IMAGETYPE_AVIF][$format];

        // Only effective rendering parameters identify a copy; context names and format aliases do not.
        $preset = [
            'width' => (int) $preset['width'],
            'height' => (int) $preset['height'],
            'mode' => (string) $preset['mode'],
            'format' => $format,
            'quality' => $format === 'png' ? 9 : (int) $preset['quality'],
        ];
        $sourceKey = hash('sha256', $file);
        $variant = hash('sha256', json_encode(['v2', $meta['mtime'], $meta['size'], $preset], JSON_THROW_ON_ERROR));
        $relative = $cache . '/v2/' . substr($sourceKey, 0, 2) . '/' . substr($sourceKey, 2, 2);
        $site = $this->siteRoot();
        $folder = $site !== null ? $this->folder($site, $relative) : null;

        if ($folder === null) {
            return $this->resolvedCache[$cacheKey] = [$url, ...$origDims];
        }

        $size = ($preset['width'] ?: 'auto') . 'x' . ($preset['height'] ?: 'auto');
        $qualityLabel = $format === 'png' ? 'lossless' : 'q' . $preset['quality'];
        $name = $size . '-' . $preset['mode'] . '-' . $qualityLabel . '-'
            . substr($sourceKey, 0, 32) . '-' . substr($variant, 0, 24) . '.' . ($format === 'jpeg' ? 'jpg' : $format);
        $target = $folder . '/' . $name;
        $resultUrl = $this->root()['root'] . '/' . $relative . '/' . $name;

        if (is_file($target) && !is_link($target) && filesize($target) > 0) {
            return $this->resolvedCache[$cacheKey] = [$resultUrl, ...$this->fileDimensions($target)];
        }

        $lockPath = $target . '.lock';
        if (is_link($lockPath)) {
            return $this->resolvedCache[$cacheKey] = [$url, ...$origDims];
        }
        $lock = @fopen($lockPath, 'c');
        if (!$lock) {
            return $this->resolvedCache[$cacheKey] = [$url, ...$origDims];
        }

        $temporary = null;
        $image = null;
        $resized = null;
        try {
            if (!flock($lock, LOCK_EX)) {
                return [$url, ...$origDims];
            }
            clearstatcache(true, $target);
            if (is_file($target) && !is_link($target) && filesize($target) > 0) {
                return $this->resolvedCache[$cacheKey] = [$resultUrl, ...$this->fileDimensions($target)];
            }

            $width = $preset['width'];
            $height = $preset['height'];
            if (!$width) $width = max(1, (int) round($height * $info[0] / $info[1]));
            if (!$height) $height = max(1, (int) round($width * $info[1] / $info[0]));
            $bound = min(1.0, 2400 / $width, 2400 / $height);
            $width = max(1, (int) floor($width * $bound));
            $height = max(1, (int) floor($height * $bound));
            // Never enlarge a small original. Keep the desired aspect ratio for cover/stretch.
            $scale = min(1.0, $info[0] / $width, $info[1] / $height);
            if ($preset['mode'] !== 'contain') {
                $width = max(1, (int) floor($width * $scale));
                $height = max(1, (int) floor($height * $scale));
            } else {
                $width = min($width, (int) $info[0]);
                $height = min($height, (int) $info[1]);
            }

            $image = new Image($file);
            $resized = match ($preset['mode']) {
                'cover' => $image->cropResize($width, $height),
                'stretch' => $image->resize($width, $height, true, Image::SCALE_FILL),
                default => $image->resize($width, $height, true, Image::SCALE_INSIDE),
            };
            if ($format === 'jpeg') {
                // JPEG cannot retain alpha: use a white background for transparent products/logos.
                $canvas = imagecreatetruecolor($resized->getWidth(), $resized->getHeight());
                imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
                imagecopy($canvas, $resized->getHandle(), 0, 0, 0, 0, $resized->getWidth(), $resized->getHeight());
                $resized->destroy();
                $resized = new Image($canvas);
            }
            $temporary = tempnam($folder, '.image-');
            if ($temporary === false) {
                return [$url, ...$origDims];
            }
            $quality = $format === 'png' ? 9 : $preset['quality'];
            if (!$resized->toFile($temporary, $outputType, ['quality' => $quality])) {
                return [$url, ...$origDims];
            }
            $check = @getimagesize($temporary);
            if (!$check || (int) $check[2] !== $outputType || $check[0] !== $resized->getWidth() || $check[1] !== $resized->getHeight()) {
                return [$url, ...$origDims];
            }
            @chmod($temporary, 0644);
            if (!@rename($temporary, $target)) {
                return [$url, ...$origDims];
            }
            $temporary = null;
            $dims = [(int) $check[0], (int) $check[1]];
            $this->dimensionsCache[$target] = $dims;

            return $this->resolvedCache[$cacheKey] = [$resultUrl, ...$dims];
        } finally {
            if (is_string($temporary) && is_file($temporary)) @unlink($temporary);
            $resized?->destroy();
            $image?->destroy();
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Размеры локального растрового файла по адресу сайта: [ширина, высота] или [0, 0] (чужой адрес, не растр, нет файла). Только заголовок файла. */
    public function dimensions(string $url): array
    {
        return $this->fileDimensions($this->localPath($url));
    }

    /** @return array{0: int, 1: int} */
    private function fileDimensions(?string $file): array
    {
        if ($file === null) {
            return [0, 0];
        }

        if (isset($this->dimensionsCache[$file])) {
            return $this->dimensionsCache[$file];
        }

        if (!is_file($file) || !is_readable($file)) {
            return $this->dimensionsCache[$file] = [0, 0];
        }

        $info = @getimagesize($file);

        return $this->dimensionsCache[$file] = ($info && (int) $info[0] > 0 && (int) $info[1] > 0)
            ? [(int) $info[0], (int) $info[1]]
            : [0, 0];
    }

    /** @return array{size: int, mtime: int, info: ?array}|null */
    private function sourceMeta(string $file): ?array
    {
        if (array_key_exists($file, $this->sourceCache)) {
            return $this->sourceCache[$file];
        }

        if (!is_file($file) || !is_readable($file)) {
            return $this->sourceCache[$file] = null;
        }

        $size = filesize($file);
        $mtime = filemtime($file);

        if ($size === false || $mtime === false || $size > 32 * 1024 * 1024) {
            return $this->sourceCache[$file] = ['size' => (int) ($size ?: 0), 'mtime' => (int) ($mtime ?: 0), 'info' => null];
        }

        $info = @getimagesize($file) ?: null;
        if ($info && (int) $info[0] > 0 && (int) $info[1] > 0) {
            $this->dimensionsCache[$file] = [(int) $info[0], (int) $info[1]];
        }

        return $this->sourceCache[$file] = ['size' => (int) $size, 'mtime' => (int) $mtime, 'info' => $info];
    }

    private function siteRoot(): ?string
    {
        if ($this->siteRoot === null) {
            $real = realpath(JPATH_ROOT);
            $this->siteRoot = $real ?: '';
        }

        return $this->siteRoot !== '' ? $this->siteRoot : null;
    }

    /** @return array{host: string, port: ?int, prefix: string, root: string} */
    private function root(): array
    {
        if ($this->rootInfo === null) {
            $rootUrl = rtrim(Uri::root(), '/');
            $parsed = parse_url($rootUrl . '/') ?: [];
            $this->rootInfo = [
                'host' => strtolower((string) ($parsed['host'] ?? '')),
                'port' => $parsed['port'] ?? null,
                'prefix' => rtrim(rawurldecode((string) ($parsed['path'] ?? '')), '/') . '/',
                'root' => $rootUrl,
            ];
        }

        return $this->rootInfo;
    }

    private function localPath(string $url): ?string
    {
        $root = $this->root();
        $source = parse_url($url);

        if (!$source || $root['host'] === '' || strtolower((string) ($source['host'] ?? '')) !== $root['host']
            || ($source['port'] ?? null) !== $root['port']) {
            return null;
        }

        $path = rawurldecode((string) ($source['path'] ?? ''));

        if (!str_starts_with($path, $root['prefix']) || str_contains($path, "\0")) {
            return null;
        }

        $site = $this->siteRoot();
        $file = $site !== null ? realpath(JPATH_ROOT . '/' . substr($path, strlen($root['prefix']))) : false;

        if (!$site || !$file || !str_starts_with($file, $site . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $file;
    }

    /** Validate each existing ancestor before creating children; never follow a symlink outside the site. */
    private function folder(string $site, string $relative): ?string
    {
        if (array_key_exists($relative, $this->folderCache)) {
            return $this->folderCache[$relative];
        }

        $folder = $site;
        foreach (explode('/', $relative) as $part) {
            $folder .= '/' . $part;
            if (!is_dir($folder) && !@mkdir($folder, 0755) && !is_dir($folder)) {
                return $this->folderCache[$relative] = null;
            }
            $real = realpath($folder);
            if (!$real || !str_starts_with($real, $site . DIRECTORY_SEPARATOR)) {
                return $this->folderCache[$relative] = null;
            }
            $folder = $real;
        }

        return $this->folderCache[$relative] = $folder;
    }

    private function memoryLimit(): int
    {
        if ($this->memLimit !== null) {
            return $this->memLimit;
        }

        $value = trim((string) ini_get('memory_limit'));
        if ($value === '' || $value === '-1') {
            return $this->memLimit = 0;
        }

        return $this->memLimit = (int) $value * match (strtolower(substr($value, -1))) {
            'g' => 1073741824, 'm' => 1048576, 'k' => 1024, default => 1,
        };
    }
}
