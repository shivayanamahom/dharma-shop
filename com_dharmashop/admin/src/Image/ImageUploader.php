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
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * Приём загруженного изображения: проверка (расширение, тип содержимого, размер), безопасное имя, сохранение в
 * images/dharmashop/<папка>/<год>/. Допустимые расширения и предел размера берутся из настроек «Медиа» Joomla.
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

        if ($extension === 'svg') {
            $content = (string) file_get_contents($file['tmp_name']);

            if (!str_contains(strtolower($content), '<svg') || preg_match('/<script|\son[a-z]+\s*=|javascript:|<foreignObject|<!ENTITY/i', $content)) {
                throw new \RuntimeException('COM_DHARMASHOP_UPLOAD_ERROR_SVG');
            }
        } else {
            $info = @getimagesize($file['tmp_name']);

            if ($info === false) {
                throw new \RuntimeException('COM_DHARMASHOP_UPLOAD_ERROR_TYPE');
            }

            [$width, $height] = [(int) $info[0], (int) $info[1]];
        }

        $folder = trim(preg_replace('/[^a-z0-9_-]/', '', strtolower($folder)) ?? '', '-_') ?: 'common';
        $relative = 'images/dharmashop/' . $folder . '/' . date('Y');
        $directory = rtrim($this->root, '/') . '/' . $relative;

        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('COM_DHARMASHOP_UPLOAD_ERROR_STORE');
        }

        $base = $this->baseName(pathinfo($original, PATHINFO_FILENAME));
        $name = $base . '.' . $extension;

        for ($i = 2; is_file($directory . '/' . $name); $i++) {
            $name = $base . '-' . $i . '.' . $extension;
        }

        if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $name)) {
            throw new \RuntimeException('COM_DHARMASHOP_UPLOAD_ERROR_STORE');
        }

        @chmod($directory . '/' . $name, 0644);

        return ['path' => $relative . '/' . $name, 'width' => $width, 'height' => $height];
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
