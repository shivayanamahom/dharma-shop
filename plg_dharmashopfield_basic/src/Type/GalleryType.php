<?php
declare(strict_types=1);
namespace Dharma\Plugin\DharmaShopField\Basic\Type;

use Dharma\Component\DharmaShop\Administrator\Image\ImageUploader;

\defined('_JEXEC') or die;

/** Additional image gallery; stored in positive field IDs and never owns the reserved main gallery. */
final class GalleryType extends BaseType
{
    public function id(): string { return 'gallery'; }
    public function store(): string { return 'media'; }

    public function normalizeSettings(array $settings): array
    {
        $form = parent::normalizeSettings($settings);
        $count = (string) ($form['gallery_max_count'] ?? 50);
        $layout = (string) ($form['gallery_layout'] ?? 'grid');
        $caption = (string) ($form['gallery_caption'] ?? '1');
        $lightbox = (string) ($form['gallery_lightbox'] ?? '1');
        $fit = (string) ($form['gallery_fit'] ?? 'cover');
        $ratio = (string) ($form['gallery_ratio'] ?? '4x3');
        $folder = strtolower(trim((string) ($form['gallery_folder'] ?? 'additional')));
        $folder = preg_replace('/[^a-z0-9_-]+/', '-', $folder) ?? '';
        $folder = trim(substr($folder, 0, 50), '-_');
        if (!preg_match('/^[1-9][0-9]{0,2}$/D', $count) || (int) $count > 100
            || !in_array($layout, ['grid', 'slider', 'list'], true)
            || !in_array($caption, ['0', '1'], true)
            || !in_array($lightbox, ['0', '1'], true)
            || !in_array($fit, ['cover', 'contain', 'original'], true)
            || !in_array($ratio, ['1x1', '4x3', '16x9'], true)) {
            throw new \InvalidArgumentException('PLG_DHARMASHOPFIELD_BASIC_GALLERY_SETTINGS_INVALID');
        }
        // Read legacy list/columns without changing stored definitions or their frontend arrangement.
        $legacyColumns = max(1, min(6, (int) ($settings['gallery_columns'] ?? 3)));
        $defaults = $layout === 'list'
            ? ['xs'=>1, 'sm'=>1, 'md'=>1, 'lg'=>1, 'xl'=>1, 'xxl'=>1]
            : ['xs'=>1, 'sm'=>1, 'md'=>2, 'lg'=>2, 'xl'=>$legacyColumns, 'xxl'=>$legacyColumns];
        $result = ['gallery_max_count'=>(int) $count, 'gallery_layout'=>$layout === 'list' ? 'grid' : $layout,
            'gallery_caption'=>(int) $caption, 'gallery_lightbox'=>(int) $lightbox, 'gallery_folder'=>$folder ?: 'additional',
            'gallery_fit'=>$fit, 'gallery_ratio'=>$ratio];
        foreach ($defaults as $breakpoint => $default) {
            $key = 'gallery_columns_' . $breakpoint;
            $columns = (string) ($form[$key] ?? $default);
            if (!in_array($columns, ['1', '2', '3', '4', '5', '6'], true)) {
                throw new \InvalidArgumentException('PLG_DHARMASHOPFIELD_BASIC_GALLERY_SETTINGS_INVALID');
            }
            $result[$key] = (int) $columns;
        }
        return $result;
    }

    public function input(object $field, array $options): \SimpleXMLElement
    {
        $xml = new \SimpleXMLElement('<field type="gallery" filter="Dharma\\Component\\DharmaShop\\Administrator\\Image\\ImageUploader::filterGallery" required="false" addfieldprefix="Dharma\\Component\\DharmaShop\\Administrator\\Field" />');
        $xml['name'] = 'f_' . (int) $field->id;
        $xml['label'] = (string) $field->title;
        $xml['translateLabel'] = 'false';
        $xml['field_id'] = (string) $field->id;
        $xml['max_count'] = (string) $this->normalizeSettings((array) $field->settings)['gallery_max_count'];
        return $xml;
    }

    public function defaultValue(object $field, array $options): mixed { return []; }

    public function decode(object $field, array $rows, ?string $text): mixed
    {
        return array_map(static fn ($row): array => (array) $row, $rows);
    }

    public function encode(object $field, array $options, mixed $value): array
    {
        if ($value === '' || $value === null) { $value = []; }
        if (!is_array($value) && !is_object($value)) { throw new \InvalidArgumentException('COM_DHARMASHOP_FIELD_VALUE_INVALID'); }
        $settings = $this->normalizeSettings((array) $field->settings);
        $items = [];
        foreach (ImageUploader::filterGallery($value) as $image) {
            $items[] = ['path'=>$image['path'], 'alt'=>$image['alt'], 'title'=>$image['title'], 'ordering'=>count($items), 'mime'=>null];
            if (count($items) > $settings['gallery_max_count']) {
                throw new \InvalidArgumentException('PLG_DHARMASHOPFIELD_BASIC_GALLERY_COUNT_INVALID');
            }
        }
        if ((int) $field->required === 1 && !$items) { throw new \InvalidArgumentException('COM_DHARMASHOP_FIELD_VALUE_REQUIRED'); }
        return ['rows'=>[], 'text'=>null, 'media'=>$items];
    }
}
