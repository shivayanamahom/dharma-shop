<?php
declare(strict_types=1);
namespace Dharma\Plugin\DharmaShopField\Basic\Type;

use Dharma\Component\DharmaShop\Administrator\Fields\FileStorage;
use Dharma\Component\DharmaShop\Administrator\Image\ImageUploader;

\defined('_JEXEC') or die;

/** Mixed local/service video gallery, stored in the existing per-field media rows. */
final class VideoType extends BaseType
{
    public function id(): string { return 'video'; }
    public function store(): string { return 'media'; }

    public function normalizeSettings(array $settings): array
    {
        $form = parent::normalizeSettings($settings);
        $extensions = preg_split('/[\s,;]+/', strtolower(trim((string) ($form['video_extensions'] ?? 'mp4,webm,ogv'))), -1, PREG_SPLIT_NO_EMPTY);
        $allowed = ['mp4','webm','ogv'];
        if (!$extensions || array_diff($extensions, $allowed)) { throw new \InvalidArgumentException('PLG_DHARMASHOPFIELD_BASIC_VIDEO_SETTINGS_INVALID'); }
        $count = $form['video_max_count'] ?? 20;
        if (!preg_match('/^\d+$/D', (string) $count) || (int) $count < 1 || (int) $count > 100) {
            throw new \InvalidArgumentException('PLG_DHARMASHOPFIELD_BASIC_VIDEO_SETTINGS_INVALID');
        }
        $layout = $form['video_layout'] ?? 'grid';
        $result = [
            'video_extensions'=>implode(',', array_unique($extensions)),
            'video_max_count'=>(int) $count,
            'video_layout'=>in_array($layout, ['grid','slider'], true) ? $layout : 'grid',
            'video_lightbox'=>(int) ($form['video_lightbox'] ?? 1) === 1 ? 1 : 0,
            'video_caption'=>(int) ($form['video_caption'] ?? 1) === 1 ? 1 : 0,
            'video_fetch_cover'=>(int) ($form['video_fetch_cover'] ?? 1) === 1 ? 1 : 0,
            'video_folder'=>$this->folder((string) ($form['video_folder'] ?? 'product')),
        ];
        foreach (['xs'=>1,'sm'=>1,'md'=>2,'lg'=>2,'xl'=>3,'xxl'=>3] as $breakpoint=>$default) {
            $value = (string) ($form['video_columns_' . $breakpoint] ?? $default);
            if (!in_array($value, ['1','2','3','4','5','6'], true)) { throw new \InvalidArgumentException('PLG_DHARMASHOPFIELD_BASIC_VIDEO_SETTINGS_INVALID'); }
            $result['video_columns_' . $breakpoint] = (int) $value;
        }
        return $result;
    }

    public static function currentSettings(array $settings): array { return (new self())->normalizeSettings($settings); }

    private function folder(string $folder): string
    {
        $folder = strtolower(trim($folder));
        $folder = preg_replace('/[^a-z0-9_-]+/', '-', $folder) ?? '';
        return trim(substr($folder, 0, 50), '-_') ?: 'product';
    }

    public function input(object $field, array $options): \SimpleXMLElement
    {
        $settings = $this->normalizeSettings((array) $field->settings);
        $xml = new \SimpleXMLElement('<field type="videosupload" filter="raw" required="false" addfieldprefix="Dharma\\Component\\DharmaShop\\Administrator\\Field" />');
        $xml['name'] = 'f_' . (int) $field->id;
        $xml['label'] = (string) $field->title;
        $xml['translateLabel'] = 'false';
        $xml['field_id'] = (string) $field->id;
        foreach ($settings as $key=>$value) { $xml[$key] = (string) $value; }
        return $xml;
    }

    public function defaultValue(object $field, array $options): mixed { return []; }
    public function decode(object $field, array $rows, ?string $text): mixed
    {
        return array_map(static function ($row): array {
            $item = (array) $row;
            $prefix = 'application/vnd.dharmashop.video.';
            $source = str_starts_with((string) ($item['mime'] ?? ''), $prefix) ? substr($item['mime'], strlen($prefix)) : 'local';
            return $item + ['source'=>$source, 'url'=>$source === 'local' ? '' : ($item['path'] ?? ''), 'poster'=>$item['alt'] ?? ''];
        }, $rows);
    }

    public function encode(object $field, array $options, mixed $value): array
    {
        if ($value === '' || $value === null) { $value = []; }
        if (!is_array($value) && !is_object($value)) { throw new \InvalidArgumentException('COM_DHARMASHOP_FIELD_VALUE_INVALID'); }
        $settings = $this->normalizeSettings((array) $field->settings);
        $storage = new FileStorage(JPATH_ROOT, (int) $field->id, $this->fileSettings($settings), 'videos', false);
        $items = []; $seen = [];
        foreach ((array) $value as $raw) {
            $item = (array) $raw;
            $source = (string) ($item['source'] ?? 'local');
            $title = $item['title'] ?? '';
            if (!is_scalar($title)) { throw new \InvalidArgumentException('COM_DHARMASHOP_FIELD_VALUE_INVALID'); }
            $title = mb_substr(trim(strip_tags((string) $title)), 0, 255);
            if ($source === 'local') {
                if (trim((string) ($item['path'] ?? '')) === '') { continue; }
                $data = $storage->inspect($item['path']);
                $mime = $data['mime'];
                $poster = $this->poster((string) ($item['poster'] ?? ''), (int) $field->id, $settings['video_folder']);
                if ($poster === '' && $settings['video_fetch_cover'] === 1) {
                    $poster = VideoMetadata::localPoster($data['path'], (int) $field->id, $settings['video_folder']);
                }
            } else {
                if (!in_array($source, ['youtube','rutube','vk'], true)) { throw new \InvalidArgumentException('PLG_DHARMASHOPFIELD_BASIC_VIDEO_PROVIDER_INVALID'); }
                $video = VideoProvider::parse((string) ($item['url'] ?? ''), $source);
                $data = ['path'=>$video['url'], 'filename'=>'', 'bytes'=>null];
                $mime = 'application/vnd.dharmashop.video.' . $source;
                $poster = $this->poster((string) ($item['poster'] ?? ''), (int) $field->id, $settings['video_folder']);
                $data['title'] = $title;
            }
            $key = $data['path'];
            if (isset($seen[$key])) { continue; }
            $seen[$key] = true;
            $items[] = ['path'=>$data['path'], 'alt'=>$poster, 'title'=>$title ?: ($data['title'] ?? ''),
                'filename'=>$data['filename'] ?? '', 'bytes'=>$data['bytes'] ?? null, 'mime'=>$mime, 'ordering'=>count($items)];
            if (count($items) > $settings['video_max_count']) { throw new \InvalidArgumentException('PLG_DHARMASHOPFIELD_BASIC_VIDEO_COUNT_INVALID'); }
        }
        if ((int) $field->required === 1 && !$items) { throw new \InvalidArgumentException('COM_DHARMASHOP_FIELD_VALUE_REQUIRED'); }
        return ['rows'=>[], 'text'=>null, 'media'=>$items];
    }

    public static function fileSettings(array $settings): array
    {
        return ['files_max_mb'=>0, 'files_extensions'=>$settings['video_extensions'],
            'files_max_count'=>$settings['video_max_count'], 'files_label'=>'caption','files_naming'=>'original',
            'files_layout'=>'list','files_columns'=>3];
    }

    private function poster(string $path, int $fieldId, string $folder): string
    {
        if ($path === '') { return ''; }
        $path = ImageUploader::filterPath($path);
        if ($path === '' || !preg_match('~^images/[^\x00-\x1F]+/field-video-posters/' . $fieldId . '/' . preg_quote($folder, '~') . '/[0-9]{4}/[a-z0-9_-]+\.(?:jpg|jpeg|png|webp)$~iD', $path)) {
            throw new \InvalidArgumentException('PLG_DHARMASHOPFIELD_BASIC_VIDEO_POSTER_INVALID');
        }
        $real = realpath(JPATH_ROOT . '/' . $path);
        $root = realpath(JPATH_ROOT . '/images');
        if (!$real || !$root || !str_starts_with($real, $root . DIRECTORY_SEPARATOR) || !is_file($real)) {
            throw new \InvalidArgumentException('PLG_DHARMASHOPFIELD_BASIC_VIDEO_POSTER_INVALID');
        }
        return $path;
    }
}
