<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Image;

use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Image\Image;
use enshrined\svgSanitize\Sanitizer;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * Приём загруженного изображения: проверка (расширение, тип содержимого, размер), безопасное имя, сохранение в
 * <папка из настроек>/<тип>/<год>/ (по умолчанию images/dharmashop). Допустимые расширения и предел размера берутся из настроек «Медиа» Joomla.
 */
final class ImageUploader
{
    /** Расширение => допустимые MIME-типы содержимого. */
    private const MIME = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'avif' => ['image/avif'],
        'bmp' => ['image/bmp', 'image/x-ms-bmp'],
        'svg' => ['image/svg+xml', 'text/xml', 'text/plain', 'application/xml'],
    ];

    /**
     * @param  Registry  $mediaParams  настройки «Медиа» Joomla (расширения, размер)
     * @param  string    $root         корень сайта
     * @param  Registry  $shopParams   настройки магазина (имена файлов: image_name_mode, image_name_prefix)
     */
    public function __construct(private readonly Registry $mediaParams, private readonly string $root, private readonly ?Registry $shopParams = null)
    {
    }

    public const DEFAULT_BASE = 'dharmashop';

    /** Допустимые типы подпапок (атрибут folder поля): произвольные имена папок создавать нельзя. */
    public const FOLDERS = ['categories', 'icons', 'seo', 'products', 'gallery', 'common'];

    /** Предел площади изображения (пикселей): защита от «бомб» распаковки при последующей обработке. */
    private const MAX_PIXELS = 80000000;

    /** Качество новых WebP-файлов; используется штатный Image API Joomla поверх GD. */
    private const WEBP_QUALITY = 82;

    /** Корневая папка загрузок внутри images/ по настройке «Папка для изображений» (латиница, цифры, - _ и вложенность через /; пусто — dharmashop). */
    public static function baseFolder(?Registry $shopParams): string
    {
        $parts = [];

        foreach (explode('/', str_replace('\\', '/', strtolower((string) $shopParams?->get('image_base_folder', self::DEFAULT_BASE)))) as $part) {
            $part = trim(preg_replace('/[^a-z0-9_-]/', '', $part) ?? '', '-_');

            if ($part !== '') {
                $parts[] = $part;
            }
        }

        return 'images/' . ($parts ? implode('/', $parts) : self::DEFAULT_BASE);
    }

    /** Подпапка внутри корневой по настройке «Структура папок»: тип/год (по умолчанию), тип/год/месяц, тип, одна папка. */
    private function subFolder(string $folder): string
    {
        return match ((string) $this->shopParams?->get('image_folder_mode', 'type_year')) {
            'type_year_month' => $folder . '/' . date('Y') . '/' . date('m'),
            'type' => $folder,
            'flat' => '',
            default => $folder . '/' . date('Y'),
        };
    }

    /** Имя файла без расширения по настройкам магазина: «оригинальное», «случайное» или «оригинальное + случайный хвост»; перед ним — префикс. */
    private function baseName(string $original): string
    {
        $mode = (string) $this->shopParams?->get('image_name_mode', 'random');
        $prefix = trim(preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $this->shopParams?->get('image_name_prefix', ''))) ?? '', '-_');
        $slug = OutputFilter::stringURLSafe($original) ?: 'image';
        $random = bin2hex(random_bytes(5));

        $base = match ($mode) {
            'original' => $slug,
            'original_random' => $slug . '-' . $random,
            default => $random,
        };

        return ($prefix !== '' ? $prefix . '-' : '') . $base;
    }

    /** @return string[] */
    public function allowedExtensions(): array
    {
        $configured = array_filter(array_map('trim', explode(',', strtolower((string) $this->mediaParams->get('image_extensions', 'bmp,gif,jpg,jpeg,png,webp,avif')))));

        return array_values(array_intersect($configured, array_keys(self::MIME)));
    }

    /** Предел размера файла в байтах: настройка «Медиа» и ограничения PHP, что меньше. */
    public function maxBytes(): int
    {
        $limits = [(int) ((float) $this->mediaParams->get('upload_maxsize', 10) * 1048576)];

        foreach (['upload_max_filesize', 'post_max_size'] as $name) {
            $bytes = self::iniBytes((string) ini_get($name));

            if ($bytes > 0) {
                $limits[] = $bytes;
            }
        }

        return max(0, min(array_filter($limits, static fn (int $v): bool => $v > 0) ?: [0]));
    }

    /**
     * @param   array   $file    элемент $_FILES
     * @param   string  $folder  подпапка (латиница, цифры, - и _)
     *
     * @return  array{path: string, width: int, height: int}
     *
     * @throws  \RuntimeException  с языковым ключом сообщения
     */
    public function store(array $file, string $folder): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new \RuntimeException('COM_DHARMASHOP_UPLOAD_ERROR_SIZE');
        }

        if ($error !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new \RuntimeException('COM_DHARMASHOP_UPLOAD_ERROR_NO_FILE');
        }

        $max = $this->maxBytes();

        if ($max > 0 && (int) $file['size'] > $max) {
            throw new \RuntimeException('COM_DHARMASHOP_UPLOAD_ERROR_SIZE');
        }

        $original = (string) ($file['name'] ?? '');
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));

        if (!in_array($extension, $this->allowedExtensions(), true)) {
            throw new \RuntimeException('COM_DHARMASHOP_UPLOAD_ERROR_TYPE');
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);

        if (!in_array($mime, self::MIME[$extension], true)) {
            throw new \RuntimeException('COM_DHARMASHOP_UPLOAD_ERROR_TYPE');
        }

        $width = $height = 0;
        $svgClean = null;

        if ($extension === 'svg') {
            $svgClean = self::cleanSvg((string) file_get_contents($file['tmp_name']));
        } else {
            $info = @getimagesize($file['tmp_name']);

            if ($info === false) {
                throw new \RuntimeException('COM_DHARMASHOP_UPLOAD_ERROR_TYPE');
            }

            [$width, $height] = [(int) $info[0], (int) $info[1]];

            if ($width < 1 || $height < 1 || $width * $height > self::MAX_PIXELS) {
                throw new \RuntimeException('COM_DHARMASHOP_UPLOAD_ERROR_DIMENSIONS');
            }

            // Файл, в который дописан PHP-код (картинка-«полиглот»), не принимаем.
            if (self::containsPhp($file['tmp_name'])) {
                throw new \RuntimeException('COM_DHARMASHOP_UPLOAD_ERROR_TYPE');
            }
        }

        $folder = in_array($folder, self::FOLDERS, true) ? $folder : 'common';
        $sub = $this->subFolder($folder);
        $relative = self::baseFolder($this->shopParams) . ($sub !== '' ? '/' . $sub : '');
        $directory = rtrim($this->root, '/') . '/' . $relative;

        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('COM_DHARMASHOP_UPLOAD_ERROR_STORE');
        }

        $this->guardBaseFolder();

        $convertedFile = null;
        $storedExtension = $extension;

        try {
            if (
                $svgClean === null
                && (int) $this->shopParams?->get('image_convert_webp', 0) === 1
                && in_array($mime, ['image/jpeg', 'image/png', 'image/avif'], true)
            ) {
                $convertedFile = self::toWebp($file['tmp_name'], $width, $height);
                $storedExtension = 'webp';
            }

            $base = $this->baseName(pathinfo($original, PATHINFO_FILENAME));
            $name = $base . '.' . $storedExtension;

            // Имя «занимаем» атомарно (режим x): два одновременных запроса не затрут друг друга.
            for ($i = 2; ($handle = @fopen($directory . '/' . $name, 'x')) === false; $i++) {
                if ($i > 200) {
                    throw new \RuntimeException('COM_DHARMASHOP_UPLOAD_ERROR_STORE');
                }

                $name = $base . '-' . $i . '.' . $storedExtension;
            }

            fclose($handle);
            $target = $directory . '/' . $name;
            $saved = match (true) {
                $svgClean !== null => file_put_contents($target, $svgClean) !== false,
                $convertedFile !== null => @copy($convertedFile, $target),
                default => move_uploaded_file($file['tmp_name'], $target),
            };

            if (!$saved) {
                @unlink($target);

                throw new \RuntimeException('COM_DHARMASHOP_UPLOAD_ERROR_STORE');
            }

            @chmod($target, 0644);

            return ['path' => $relative . '/' . $name, 'width' => $width, 'height' => $height];
        } finally {
            if ($convertedFile !== null && is_file($convertedFile)) {
                @unlink($convertedFile);
            }
        }
    }

    /** Конвертирует поддерживаемые Joomla растровые форматы через штатный Image API и проверяет результат. */
    private static function toWebp(string $source, int $width, int $height): string
    {
        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            throw new \RuntimeException('COM_DHARMASHOP_UPLOAD_ERROR_WEBP');
        }

        $temporary = tempnam(sys_get_temp_dir(), 'dharmashop-webp-');

        if ($temporary === false) {
            throw new \RuntimeException('COM_DHARMASHOP_UPLOAD_ERROR_WEBP');
        }

        $image = null;

        try {
            $image = new Image($source);

            if (!$image->toFile($temporary, IMAGETYPE_WEBP, ['quality' => self::WEBP_QUALITY])) {
                throw new \RuntimeException('COM_DHARMASHOP_UPLOAD_ERROR_WEBP');
            }

            $result = @getimagesize($temporary);

            if (
                $result === false
                || ($result['mime'] ?? '') !== 'image/webp'
                || (int) $result[0] !== $width
                || (int) $result[1] !== $height
            ) {
                throw new \RuntimeException('COM_DHARMASHOP_UPLOAD_ERROR_WEBP');
            }

            return $temporary;
        } catch (\Throwable $error) {
            @unlink($temporary);

            if ($error instanceof \RuntimeException && $error->getMessage() === 'COM_DHARMASHOP_UPLOAD_ERROR_WEBP') {
                throw $error;
            }

            throw new \RuntimeException('COM_DHARMASHOP_UPLOAD_ERROR_WEBP', 0, $error);
        } finally {
            $image?->destroy();
        }
    }

    /** SVG чистим штатным санитайзером Joomla (скрипты, обработчики событий, внешние ссылки); пустой результат — отказ. */
    private static function cleanSvg(string $content): string
    {
        if (!str_contains(strtolower($content), '<svg') || stripos($content, '<!ENTITY') !== false) {
            throw new \RuntimeException('COM_DHARMASHOP_UPLOAD_ERROR_SVG');
        }

        $sanitizer = new Sanitizer();
        $sanitizer->removeRemoteReferences(true);
        $clean = $sanitizer->sanitize($content);

        // Внешние стили и ресурсы в SVG не нужны: @import и url() не на внутренний якорь отклоняем.
        if (is_string($clean) && preg_match('/@import|url\s*\(\s*[\'"]?\s*(?!#)/i', $clean)) {
            throw new \RuntimeException('COM_DHARMASHOP_UPLOAD_ERROR_SVG');
        }

        if (!is_string($clean) || !str_contains(strtolower($clean), '<svg')) {
            throw new \RuntimeException('COM_DHARMASHOP_UPLOAD_ERROR_SVG');
        }

        return $clean;
    }

    /** Есть ли в файле открывающий тег PHP (в сжатых данных картинок такая последовательность не встречается). */
    private static function containsPhp(string $path): bool
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return true;
        }

        $tail = '';

        while (!feof($handle)) {
            $chunk = $tail . (string) fread($handle, 1048576);

            if (stripos($chunk, '<?php') !== false) {
                fclose($handle);

                return true;
            }

            $tail = substr($chunk, -4);
        }

        fclose($handle);

        return false;
    }

    /**
     * В корневой папке загрузок кладём .htaccess: скрипты не исполняются, браузеру запрещено угадывать тип,
     * SVG отдаётся с песочницей (на случай прямого открытия). Для nginx действует настройка сервера.
     */
    private function guardBaseFolder(): void
    {
        $file = rtrim($this->root, '/') . '/' . self::baseFolder($this->shopParams) . '/.htaccess';

        if (is_file($file)) {
            return;
        }

        $rules = "# Dharma Shop: загруженные файлы — только данные, не код.\n"
            . "<FilesMatch \"\\.(php|phtml|phar|php[0-9]?|pl|py|cgi|sh)$\">\n    Require all denied\n</FilesMatch>\n"
            . "Options -ExecCGI -Indexes\n"
            . "<IfModule mod_headers.c>\n    Header set X-Content-Type-Options \"nosniff\"\n"
            . "    <FilesMatch \"\\.svg$\">\n        Header set Content-Security-Policy \"default-src 'none'; style-src 'unsafe-inline'; sandbox\"\n    </FilesMatch>\n</IfModule>\n";

        @file_put_contents($file, $rules);
    }

    /**
     * Фильтр значения поля изображения (для атрибута filter полей формы): только относительный путь внутри images/
     * к файлу с допустимым расширением, без «..», схем (javascript:, http:) и служебных символов; остальное — пустая строка.
     * Хвост «#joomlaImage://…» штатного поля Media сохраняется, если он выглядит как штатный.
     */
    public static function filterPath(mixed $value): string
    {
        $value = trim((string) $value);
        $path = (string) strstr($value . '#', '#', true);
        $hash = substr($value, strlen($path));

        if ($path === '') {
            return '';
        }

        $extensions = implode('|', array_keys(self::MIME));

        if (
            !preg_match('#^images/[^\x00-\x1f<>"\'`\\:?*|]+\.(?:' . $extensions . ')$#iu', $path)
            || str_contains($path, '..') || str_contains($path, '//')
        ) {
            return '';
        }

        return $path . (preg_match('#^\#joomlaImage://local-[a-z0-9_-]+/[^\s<>"\'`]*$#i', $hash) ? $hash : '');
    }

    /**
     * Фильтр значения поля-галереи (атрибут filter): список элементов {path, alt, title}; пути проходят filterPath,
     * пустые и повторяющиеся отбрасываются, подписи очищаются от тегов; не больше 100 изображений.
     *
     * @return  array<int, array{path: string, alt: string, title: string}>
     */
    public static function filterGallery(mixed $value): array
    {
        $result = [];
        $seen = [];

        // Данные формы приходят из Registry: ассоциативные массивы там становятся объектами, поэтому приводим к массивам.
        $value = is_array($value) || is_object($value) ? (json_decode((string) json_encode($value), true) ?: []) : [];

        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }

            $path = trim((string) strstr(self::filterPath($item['path'] ?? '') . '#', '#', true));

            if ($path === '' || isset($seen[$path])) {
                continue;
            }

            $seen[$path] = true;
            $clean = static fn (mixed $text): string => mb_substr(trim(strip_tags((string) $text)), 0, 255);
            $result[] = ['path' => $path, 'alt' => $clean($item['alt'] ?? ''), 'title' => $clean($item['title'] ?? '')];

            if (count($result) >= 100) {
                break;
            }
        }

        return $result;
    }

    private static function iniBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (float) $value;

        return (int) match ($unit) {
            'g' => $number * 1073741824,
            'm' => $number * 1048576,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
