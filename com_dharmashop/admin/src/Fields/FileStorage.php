<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Fields;

use Dharma\Component\DharmaShop\Administrator\Image\ImageUploader;
use Joomla\CMS\Component\ComponentHelper;

\defined('_JEXEC') or die;

/** Public catalogue documents. Never passes attachments through the image pipeline. */
final class FileStorage
{
    public const MIME = [
        'pdf'=>['application/pdf'], 'txt'=>['text/plain'], 'csv'=>['text/plain','text/csv','application/csv'],
        'rtf'=>['application/rtf','text/rtf','text/plain'],
        'doc'=>['application/msword','application/x-ole-storage','application/CDFV2'],
        'xls'=>['application/vnd.ms-excel','application/x-ole-storage','application/CDFV2'],
        'ppt'=>['application/vnd.ms-powerpoint','application/x-ole-storage','application/CDFV2'],
        'docx'=>['application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/zip'],
        'xlsx'=>['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/zip'],
        'pptx'=>['application/vnd.openxmlformats-officedocument.presentationml.presentation','application/zip'],
        'odt'=>['application/vnd.oasis.opendocument.text','application/zip'],
        'ods'=>['application/vnd.oasis.opendocument.spreadsheet','application/zip'],
        'odp'=>['application/vnd.oasis.opendocument.presentation','application/zip'],
        'zip'=>['application/zip','application/x-zip-compressed'],
        '7z'=>['application/x-7z-compressed'], 'rar'=>['application/x-rar','application/vnd.rar'],
        'jpg'=>['image/jpeg'], 'jpeg'=>['image/jpeg'], 'png'=>['image/png'], 'webp'=>['image/webp'],
    ];
    public const DEFAULT_EXTENSIONS = 'pdf,doc,docx,xls,xlsx,ppt,pptx,odt,ods,odp,rtf,txt,csv,zip,jpg,jpeg,png,webp';

    public function __construct(private readonly string $root, private readonly int $fieldId, private readonly array $settings) {}

    public static function settings(array $settings): array
    {
        $extensions = preg_split('/[\s,;]+/', strtolower(trim((string) ($settings['files_extensions'] ?? self::DEFAULT_EXTENSIONS))), -1, PREG_SPLIT_NO_EMPTY);
        if (!$extensions || array_diff($extensions, array_keys(self::MIME))) { throw new \InvalidArgumentException('PLG_DHARMASHOPFIELD_BASIC_FILES_SETTINGS_INVALID'); }
        $size = $settings['files_max_mb'] ?? 10;
        $count = $settings['files_max_count'] ?? 10;
        if (!is_numeric($size) || !is_finite((float) $size) || (float) $size < 0.1 || (float) $size > 1024
            || !preg_match('/^\d+$/D', (string) $count) || (int) $count < 1 || (int) $count > 100
            || !in_array($settings['files_label'] ?? 'caption', ['caption','filename'], true)
            || !in_array($settings['files_layout'] ?? 'list', ['list','grid'], true)
            || !in_array((string) ($settings['files_columns'] ?? 3), ['2','3','4','5','6'], true)) {
            throw new \InvalidArgumentException('PLG_DHARMASHOPFIELD_BASIC_FILES_SETTINGS_INVALID');
        }
        return ['files_extensions'=>implode(',', array_unique($extensions)), 'files_max_mb'=>(float) $size,
            'files_max_count'=>(int) $count, 'files_label'=>$settings['files_label'] ?? 'caption',
            'files_layout'=>$settings['files_layout'] ?? 'list',
            'files_columns'=>(int) ($settings['files_columns'] ?? 3),
            'files_show_size'=>(int) (bool) ($settings['files_show_size'] ?? 1),
            'files_show_extension'=>(int) (bool) ($settings['files_show_extension'] ?? 1),
            'files_new_tab'=>(int) (bool) ($settings['files_new_tab'] ?? 1)];
    }

    public function maxBytes(bool $upload = false): int
    {
        $bytes = (int) round((float) $this->settings['files_max_mb'] * 1048576);
        if ($upload) {
            $server = (new ImageUploader(ComponentHelper::getParams('com_media'), $this->root))->maxBytes();
            if ($server > 0) { $bytes = min($bytes, $server); }
        }
        return $bytes;
    }

    /** Relative local paths only; realpath is checked separately. */
    public static function path(mixed $value): string
    {
        if (!is_scalar($value)) { return ''; }
        $path = explode('#', trim((string) $value), 2)[0];
        return preg_match('~^images/[^\x00-\x20<>"\'`\\\\:?*|%]+\.[a-z0-9]+$~iuD', $path)
            && !str_contains($path, '..') && !str_contains($path, '//') ? $path : '';
    }

    /** Metadata comes from the disk, never from hidden form inputs. */
    public function inspect(mixed $value): array
    {
        $path = self::path($value);
        $images = realpath($this->root . '/images');
        $file = $path !== '' ? realpath($this->root . '/' . $path) : false;
        if (!$images || !$file || !str_starts_with($file, $images . DIRECTORY_SEPARATOR) || !is_file($file)) {
            throw new \InvalidArgumentException('PLG_DHARMASHOPFIELD_BASIC_FILES_PATH_INVALID');
        }
        $metadata = $this->validate($file, strtolower(pathinfo($path, PATHINFO_EXTENSION)), false);
        $filename = basename($path);
        if (preg_match('~^images/dharmashop/files/\d+/\d{4}/[a-f0-9]{2}/[a-f0-9]{2}/[a-f0-9]{16}-(.+)$~uD', $path, $match)) { $filename = $match[1]; }
        return ['path'=>$path, 'filename'=>$filename] + $metadata;
    }

    private function validate(string $file, string $extension, bool $upload): array
    {
        if (!in_array($extension, explode(',', $this->settings['files_extensions']), true) || !isset(self::MIME[$extension])) {
            throw new \InvalidArgumentException('PLG_DHARMASHOPFIELD_BASIC_FILES_TYPE_INVALID');
        }
        $bytes = filesize($file);
        if ($bytes === false || $bytes <= 0 || $bytes > $this->maxBytes($upload)) { throw new \InvalidArgumentException('PLG_DHARMASHOPFIELD_BASIC_FILES_SIZE_INVALID'); }
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file);
        if (!in_array($mime, self::MIME[$extension], true)) { throw new \InvalidArgumentException('PLG_DHARMASHOPFIELD_BASIC_FILES_TYPE_INVALID'); }
        // OOXML/ODF containers must match their extension; do not unpack archives.
        if (in_array($extension, ['docx','xlsx','pptx','odt','ods','odp'], true)) {
            $zip = new \ZipArchive();
            if ($zip->open($file) !== true) { throw new \InvalidArgumentException('PLG_DHARMASHOPFIELD_BASIC_FILES_TYPE_INVALID'); }
            $entry = match ($extension) { 'docx'=>'word/document.xml', 'xlsx'=>'xl/workbook.xml', 'pptx'=>'ppt/presentation.xml', default=>'mimetype' };
            $valid = $zip->locateName($entry) !== false;
            if (in_array($extension, ['docx','xlsx','pptx'], true)) { $valid = $valid && $zip->locateName('[Content_Types].xml') !== false; }
            if (in_array($extension, ['odt','ods','odp'], true)) { $valid = $valid && $zip->getFromName('mimetype', 128) === self::MIME[$extension][0]; }
            $zip->close();
            if (!$valid) { throw new \InvalidArgumentException('PLG_DHARMASHOPFIELD_BASIC_FILES_TYPE_INVALID'); }
        }
        return ['bytes'=>(int) $bytes, 'mime'=>$mime];
    }

    public function store(array $upload): array
    {
        if (in_array((int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            throw new \InvalidArgumentException('PLG_DHARMASHOPFIELD_BASIC_FILES_SIZE_INVALID');
        }
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($upload['tmp_name'] ?? ''))) {
            throw new \InvalidArgumentException('COM_DHARMASHOP_UPLOAD_ERROR_NO_FILE');
        }
        $original = basename(str_replace('\\', '/', (string) ($upload['name'] ?? '')));
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $metadata = $this->validate($upload['tmp_name'], $extension, true);
        $stem = preg_replace('/[^\pL\pN_.()-]+/u', '-', pathinfo($original, PATHINFO_FILENAME)) ?? '';
        $stem = trim(str_replace('.', '-', $stem), '.-');
        $filename = mb_substr($stem !== '' ? $stem : 'file', 0, 120) . '.' . $extension;
        $random = bin2hex(random_bytes(8));
        $base = 'images/dharmashop/files';
        $directory = $base . '/' . $this->fieldId . '/' . date('Y') . '/' . substr($random, 0, 2) . '/' . substr($random, 2, 2);
        // Never follow symlinks while creating the managed storage tree.
        $current = $this->root;
        foreach (explode('/', $directory) as $part) {
            $current .= '/' . $part;
            if (is_link($current) || (!is_dir($current) && !mkdir($current, 0755) && !is_dir($current))) { throw new \RuntimeException('COM_DHARMASHOP_UPLOAD_ERROR_STORE'); }
        }
        $guard = $this->root . '/' . $base . '/.htaccess';
        if (!is_file($guard)) {
            $rules = "Options -ExecCGI -Indexes\nSetHandler none\n<IfModule mod_headers.c>\nHeader set X-Content-Type-Options nosniff\n</IfModule>\n<FilesMatch \"\\.(php[0-9]?|phtml|phar|cgi|pl|py|sh|html?|svg)$\">\nRequire all denied\n</FilesMatch>\n";
            if (file_put_contents($guard, $rules, LOCK_EX) === false) { throw new \RuntimeException('COM_DHARMASHOP_UPLOAD_ERROR_STORE'); }
        }
        $path = $directory . '/' . $random . '-' . $filename;
        if (!move_uploaded_file($upload['tmp_name'], $this->root . '/' . $path)) { throw new \RuntimeException('COM_DHARMASHOP_UPLOAD_ERROR_STORE'); }
        return ['path'=>$path, 'filename'=>$filename] + $metadata;
    }

    /** Additive installer migration; DDL runs outside entity save transactions. */
    public static function upgrade(\Joomla\Database\DatabaseInterface $db): void
    {
        $table = $db->replacePrefix('#__dharmashop_field_media');
        if (!in_array($table, $db->getTableList(), true)) { return; }
        $columns = $db->getTableColumns($table, false);
        foreach (['filename'=>'varchar(255) NULL', 'bytes'=>'bigint unsigned NULL', 'mime'=>'varchar(127) NULL'] as $name=>$type) {
            if (!isset($columns[$name])) { $db->setQuery('ALTER TABLE ' . $db->quoteName($table) . ' ADD ' . $db->quoteName($name) . ' ' . $type)->execute(); }
        }
        $db->setQuery('CREATE TABLE IF NOT EXISTS `#__dharmashop_category_field_media` (
            `id` bigint unsigned NOT NULL AUTO_INCREMENT, `category_id` int unsigned NOT NULL,
            `field_id` int unsigned NOT NULL, `path` varchar(500) NOT NULL, `alt` varchar(255) NULL,
            `title` varchar(255) NULL, `filename` varchar(255) NULL, `bytes` bigint unsigned NULL,
            `mime` varchar(127) NULL, `ordering` int NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`), KEY `idx_category` (`category_id`,`field_id`,`ordering`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci')->execute();
    }
}
