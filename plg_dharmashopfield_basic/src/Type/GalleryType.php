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
        $columns = (string) ($form['gallery_columns'] ?? 3);
        $layout = (string) ($form['gallery_layout'] ?? 'grid');
        $caption = (string) ($form['gallery_caption'] ?? '1');
        $folder = strtolower(trim((string) ($form['gallery_folder'] ?? 'additional')));
        $folder = preg_replace('/[^a-z0-9_-]+/', '-', $folder) ?? '';
        $folder = trim(substr($folder, 0, 50), '-_');
        if (!preg_match('/^[1-9][0-9]{0,2}$/D', $count) || (int) $count > 100
            || !in_array($columns, ['2','3','4','5','6'], true)
            || !in_array($layout, ['grid','list'], true)
            || !in_array($caption, ['0','1'], true)) {
            throw new \InvalidArgumentException('PLG_DHARMASHOPFIELD_BASIC_GALLERY_SETTINGS_INVALID');
        }
        return ['gallery_max_count'=>(int) $count, 'gallery_layout'=>$layout, 'gallery_columns'=>(int) $columns,
            'gallery_caption'=>(int) $caption, 'gallery_folder'=>$folder ?: 'additional'];
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
