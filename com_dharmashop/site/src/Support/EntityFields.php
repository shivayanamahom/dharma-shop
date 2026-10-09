<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Support;

use Joomla\CMS\Factory;
use Dharma\Component\DharmaShop\Administrator\Fields\FileStorage;
use Dharma\Component\DharmaShop\Administrator\Fields\TypeRegistry;
use Dharma\Component\DharmaShop\Administrator\Image\ImageUrl;
use Dharma\Component\DharmaShop\Administrator\Image\ImageUploader;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** Batch characteristics for product/category cards and full pages. */
final class EntityFields
{
    public static function attach(DatabaseInterface $db, array $items, string $context, string $target = 'product'): void
    {
        if (!in_array($target, ['product', 'category'], true)) { throw new \InvalidArgumentException('Unknown field target.'); }
        if (!in_array($context, ['card', 'page'], true)) {
            throw new \InvalidArgumentException('Unknown field display context.');
        }
        $products = [];
        foreach ($items as $item) {
            $item->fields = [];
            $products[(int) $item->id] = $item;
        }
        if (!$products) { return; }

        $levels = array_map('intval', Factory::getApplication()->getIdentity()->getAuthorisedViewLevels()) ?: [0];
        $query = $db->createQuery()->select($db->quoteName([
            'f.id', 'f.alias', 'f.title', 'f.type', 'f.unit', 'f.in_card', 'f.in_page', 'f.params', 'f.all_categories', 'f.group_id',
        ]))->select($db->quoteName('g.title', 'group_title'))->from($db->quoteName('#__dharmashop_fields', 'f'))
            ->join('LEFT', $db->quoteName('#__dharmashop_fieldgroups', 'g') . ' ON g.id = f.group_id')
            ->where('f.target = ' . $db->quote($target))->where('f.state = 1')->where('f.level = 1')
            ->where('f.access IN (' . implode(',', $levels) . ')')->where('(f.group_id = 0 OR (g.state = 1 AND g.target = f.target))')
            ->order('COALESCE(g.ordering, 0), f.group_id, f.ordering, f.id');
        $fields = [];
        // Editor content and additional galleries belong to the full page, including legacy definitions with load_card=1.
        if ($context === 'card') { $query->where('f.type NOT IN (' . $db->quote('editor') . ', ' . $db->quote('gallery') . ')'); }
        foreach ($db->setQuery($query)->loadObjectList() as $field) {
            $settings = new Registry($field->params);
            if ((int) $settings->get('load_' . $context, 1) !== 1) { continue; }
            $field->settings = $settings;
            $fields[(int) $field->id] = $field;
        }
        if (!$fields) { return; }

        // The existing category index includes primary/additional categories and ancestors.
        $applicable = '(f.all_categories = 1 OR EXISTS (SELECT 1 FROM '
            . $db->quoteName('#__dharmashop_category_fields', 'cf')
            . ' JOIN ' . $db->quoteName('#__dharmashop_product_categories', 'pc') . ' ON pc.catid = cf.catid'
            . ' WHERE cf.field_id = f.id AND pc.product_id = v.product_id))';
        $column = $target === 'product' ? 'product_id' : 'category_id';
        $simple = $target === 'product' ? '#__dharmashop_field_values' : '#__dharmashop_category_field_values';
        $long = $target === 'product' ? '#__dharmashop_field_texts' : '#__dharmashop_category_field_texts';
        if ($target === 'category') {
            $applicable = '(f.all_categories = 1 OR EXISTS (SELECT 1 FROM '
                . $db->quoteName('#__dharmashop_category_fields', 'cf')
                . ' WHERE cf.field_id = f.id AND cf.catid = v.category_id))';
        }
        $productIds = implode(',', array_keys($products));
        $fieldIds = implode(',', array_keys($fields));
        $base = static function (string $table) use ($db, $column, $productIds, $fieldIds, $applicable, $target) {
            $query = $db->createQuery()->from($db->quoteName($table, 'v'))
                ->join('INNER', $db->quoteName('#__dharmashop_fields', 'f') . ' ON f.id = v.field_id')
                ->where('v.' . $column . ' IN (' . $productIds . ')')->where('v.field_id IN (' . $fieldIds . ')')
                ->where($applicable);
            if ($target === 'product') { $query->where('v.variant_id IS NULL'); }
            return $query;
        };
        $query = $base($simple)->select('v.' . $column . ' AS entity_id')->select($db->quoteName([
            'v.field_id', 'v.option_id', 'v.num', 'v.num_end', 'v.str', 'o.title', 'o.image',
        ]))->join('LEFT', $db->quoteName('#__dharmashop_field_options', 'o') . ' ON o.id = v.option_id AND o.field_id = v.field_id')
            ->order('v.' . $column . ', o.ordering, v.id');
        $values = [];
        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            $field = $fields[(int) $row->field_id];
            if ($field->type === 'editor') { continue; }
            $text = null;
            $image = '';
            if ($row->option_id !== null) {
                if ($row->title === null) { continue; }
                $text = (string) $row->title;
                $image = MediaUrl::absolute((string) $row->image);
            } elseif ($field->type === 'checkbox') {
                if ($row->num !== null) { $text = Text::_((float) $row->num !== 0.0 ? 'JYES' : 'JNO'); }
            } elseif ($field->type === 'number' && $row->num !== null) {
                $text = (string) $row->num;
                if (str_contains($text, '.')) { $text = rtrim(rtrim($text, '0'), '.'); }
                $text = str_replace('.', ',', $text);
            } elseif ($field->type === 'range' && $row->num !== null && $row->num_end !== null) {
                $formatNumber = static fn (string $number): string => str_replace('.', ',', rtrim(rtrim($number, '0'), '.'));
                $text = $formatNumber((string) $row->num) . " \u{2013} " . $formatNumber((string) $row->num_end);
            } elseif ($field->type === 'text' && $row->str !== null) {
                $text = (string) $row->str;
            }
            if ($text === null || $text === '') { continue; }
            if ($field->unit !== '') { $text .= ' ' . $field->unit; }
            $values[(int) $row->entity_id][(int) $row->field_id][] = (object) ['text' => $text, 'image' => $image, 'html' => false];
        }
        if (array_filter($fields, static fn (object $field): bool => $field->type === 'editor')) {
            $query = $base($long)->select('v.' . $column . ' AS entity_id')->select($db->quoteName(['v.field_id', 'v.value']))->order('v.id');
            foreach ($db->setQuery($query)->loadObjectList() as $row) {
                if ($fields[(int) $row->field_id]->type !== 'editor' || trim((string) $row->value) === '') { continue; }
                $values[(int) $row->entity_id][(int) $row->field_id][] = (object) ['text' => (string) $row->value, 'image' => '', 'html' => true];
            }
        }
        $mediaFields = array_filter($fields, static fn (object $field): bool => in_array($field->type, ['files','gallery'], true));
        if ($mediaFields) {
            $media = $target === 'product' ? '#__dharmashop_field_media' : '#__dharmashop_category_field_media';
            $query = $base($media)->select('v.' . $column . ' AS entity_id')
                ->select($db->quoteName(['v.field_id','v.path','v.alt','v.title','v.filename','v.bytes','v.mime']))
                ->where('v.field_id IN (' . implode(',', array_keys($mediaFields)) . ')')->order('v.ordering, v.id');
            foreach ($db->setQuery($query)->loadObjectList() as $row) {
                $field = $mediaFields[(int) $row->field_id];
                if ($field->type === 'gallery') {
                    $path = ImageUploader::filterPath($row->path);
                    if ($path === '') { continue; }
                    $imageContext = $context === 'card' ? 'field.gallery.card' : 'field.gallery.page';
                    $values[(int) $row->entity_id][(int) $row->field_id][] = (object) [
                        'text'=>(string) ($row->alt ?: $row->title ?: ''), 'image'=>ImageUrl::resolve($path, $imageContext),
                        'url'=>ImageUrl::resolve($path, 'field.gallery.lightbox'), 'html'=>false,
                    ];
                } else {
                    $path = FileStorage::path($row->path);
                    $url = $path !== '' ? MediaUrl::absolute(FileStorage::urlPath($path)) : '';
                    if ($url === '') { continue; }
                    $filename = (string) ($row->filename ?: basename($path));
                    $label = $field->settings->get('files_label', 'caption') === 'caption' && trim((string) $row->title) !== '' ? (string) $row->title : $filename;
                    $values[(int) $row->entity_id][(int) $row->field_id][] = (object) [
                        'text'=>$label, 'image'=>'', 'html'=>false, 'url'=>$url, 'path'=>$path,
                        'filename'=>$filename, 'caption'=>(string) $row->title,
                        'bytes'=>(int) $row->bytes, 'mime'=>(string) $row->mime,
                        'extension'=>strtoupper(pathinfo($path, PATHINFO_EXTENSION)),
                    ];
                }
            }
        }
        foreach ($products as $id => $item) {
            foreach ($fields as $fieldId => $field) {
                if (empty($values[$id][$fieldId])) { continue; }
                $format = (string) $field->settings->get('format_' . $context, 'comma');
                $item->fields[$field->alias] = (object) [
                    'id' => $fieldId, 'alias' => $field->alias, 'title' => $field->title, 'type' => $field->type,
                    'group_id' => (int) $field->group_id, 'group_title' => (string) ($field->group_title ?? ''),
                    'show' => (int) $field->{'in_' . $context} === 1,
                    'show_title' => (int) $field->settings->get('show_title_' . $context, 1) === 1,
                    'show_intro' => $target === 'product' && $context === 'page' && (int) $field->settings->get('show_intro', 0) === 1,
                    'format' => in_array($format, ['comma', 'list', 'image', 'image_title'], true) ? $format : 'comma',
                    'values' => $values[$id][$fieldId],
                    'file_settings' => $field->type === 'files' ? FileStorage::settings($field->settings->toArray()) : [],
                    'gallery_settings' => $field->type === 'gallery' ? TypeRegistry::current()->get('gallery')->normalizeSettings($field->settings->toArray()) : [],
                ];
            }
        }
    }
}
