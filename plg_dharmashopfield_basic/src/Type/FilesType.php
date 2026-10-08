<?php
declare(strict_types=1);
namespace Dharma\Plugin\DharmaShopField\Basic\Type;
use Dharma\Component\DharmaShop\Administrator\Fields\FileStorage;
\defined('_JEXEC') or die;

/** Files use media rows, keeping the reserved field_id=0 main gallery untouched. */
final class FilesType extends BaseType
{
    public function id(): string { return 'files'; }
    public function store(): string { return 'media'; }
    public function normalizeSettings(array $settings): array { return FileStorage::settings($settings); }
    public function input(object $field, array $options): \SimpleXMLElement
    {
        $xml = new \SimpleXMLElement('<field type="filesupload" filter="raw" required="false" addfieldprefix="Dharma\\Component\\DharmaShop\\Administrator\\Field" />');
        $xml['name'] = 'f_' . (int) $field->id;
        $xml['label'] = (string) $field->title;
        $xml['translateLabel'] = 'false';
        $xml['field_id'] = (string) $field->id;
        foreach ($this->normalizeSettings($field->settings) as $key=>$value) { $xml[$key] = (string) $value; }
        // Required list/count/path checks belong to encode(), not native hidden input validation.
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
        $settings = $this->normalizeSettings($field->settings);
        $storage = new FileStorage(JPATH_ROOT, (int) $field->id, $settings);
        $items = []; $seen = [];
        foreach ((array) $value as $item) {
            $item = (array) $item;
            if (($item['path'] ?? '') === '') { continue; }
            $data = $storage->inspect($item['path']);
            if (isset($seen[$data['path']])) { continue; }
            $seen[$data['path']] = true;
            $caption = $item['title'] ?? '';
            if (!is_scalar($caption)) { throw new \InvalidArgumentException('COM_DHARMASHOP_FIELD_VALUE_INVALID'); }
            $data['title'] = mb_substr(trim(strip_tags((string) $caption)), 0, 255);
            $data['ordering'] = count($items);
            $items[] = $data;
            if (count($items) > $settings['files_max_count']) { throw new \InvalidArgumentException('PLG_DHARMASHOPFIELD_BASIC_FILES_COUNT_INVALID'); }
        }
        if ((int) $field->required === 1 && !$items) { throw new \InvalidArgumentException('COM_DHARMASHOP_FIELD_VALUE_REQUIRED'); }
        return ['rows'=>[], 'text'=>null, 'media'=>$items];
    }
}
