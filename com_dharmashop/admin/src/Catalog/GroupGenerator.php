<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Catalog;

use Dharma\Component\DharmaShop\Administrator\Fields\FieldValues;
use Dharma\Component\DharmaShop\Administrator\Image\ImageUploader;
use Dharma\Component\DharmaShop\Administrator\Pricing\PriceResolver;
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/** A bounded draft of ordinary products. Preparation never writes catalog data. */
final class GroupGenerator
{
    public const LIMIT = 100;
    public const DEFAULT_TEMPLATE = '{group.title} {variant.values}';

    public function __construct(private readonly DatabaseInterface $db) {}

    public function axes(array $ids): array
    {
        $ids = (new GroupService($this->db))->validateAxes($ids);
        $axes = [];
        foreach ($ids as $id) {
            $axis = $this->db->setQuery('SELECT id,title FROM #__dharmashop_fields WHERE id=' . $id)->loadObject();
            $axis->options = $this->db->setQuery('SELECT id,title FROM #__dharmashop_field_options WHERE field_id=' . $id . ' ORDER BY ordering,id')->loadAssocList('id', 'title');
            $axes[] = $axis;
        }
        return $axes;
    }

    /** Plain text, one substitution pass: field values can never become executable templates. */
    public function title(string $template, string $groupTitle, array $axes, array $row): string
    {
        $values = [];
        $replace = ['{group.title}' => $groupTitle];
        foreach ($axes as $axis) {
            $value = (string) ($axis->options[(int) ($row['axis_' . $axis->id] ?? 0)] ?? '');
            $replace['{field.' . $axis->id . '}'] = $value;
            if ($value !== '') { $values[] = $value; }
        }
        $replace['{variant.values}'] = implode(' ', $values);
        $template = trim($template) ?: self::DEFAULT_TEMPLATE;
        preg_match_all('/\{[^{}]+\}/u', $template, $tokens);
        foreach ($tokens[0] as $token) {
            if (!array_key_exists($token, $replace)) { throw new \RuntimeException('COM_DHARMASHOP_GENERATOR_TEMPLATE_INVALID'); }
        }
        return trim(preg_replace('/\s+/u', ' ', strip_tags(strtr($template, $replace))) ?? '');
    }

    /** Append missing combinations to the draft, preserving manually edited rows. */
    public function prepare(int $id, array $data): array
    {
        $axes = $this->axes((array) ($data['axes'] ?? []));
        $rows = array_values((array) ($data['executions'] ?? []));
        if (count($rows) > self::LIMIT) { throw new \RuntimeException('COM_DHARMASHOP_GENERATOR_LIMIT'); }
        $seen = [];
        foreach ((new GroupService($this->db))->members($id) as $member) { $seen[$member->combo_hash] = true; }
        foreach ($rows as $row) { $seen[GroupService::hash($this->values($axes, (array) $row))] = true; }
        $combinations = [[]];
        foreach ($axes as $axis) {
            $pool = array_values(array_unique(array_map('intval', (array) ($data['generation_values']['f_' . $axis->id] ?? []))));
            if (!$pool) { throw new \RuntimeException('COM_DHARMASHOP_GENERATOR_VALUES_REQUIRED'); }
            foreach ($pool as $option) {
                if (!isset($axis->options[$option])) { throw new \RuntimeException('COM_DHARMASHOP_GROUP_COMBINATION_REQUIRED'); }
            }
            if (count($combinations) * count($pool) > self::LIMIT) { throw new \RuntimeException('COM_DHARMASHOP_GENERATOR_LIMIT'); }
            $next = [];
            foreach ($combinations as $combination) {
                foreach ($pool as $option) { $next[] = $combination + ['axis_' . $axis->id => $option]; }
            }
            $combinations = $next;
        }
        $added = $skipped = 0;
        foreach ($combinations as $row) {
            $hash = GroupService::hash($this->values($axes, $row));
            if (isset($seen[$hash])) { $skipped++; continue; }
            $rows[] = $row + ['custom_title' => '', 'sku' => '', 'price' => $data['generation_price'] ?? 0,
                'stock_qty' => $data['generation_stock'] ?? 0, 'product_state' => $data['generation_state'] ?? 1, 'image' => ''];
            $seen[$hash] = true;
            $added++;
        }
        if (count($rows) > self::LIMIT) { throw new \RuntimeException('COM_DHARMASHOP_GENERATOR_LIMIT'); }
        // Validate the formula before returning a preview (including an empty batch).
        $this->title((string) ($data['title_template'] ?? ''), (string) ($data['title'] ?? ''), $axes, $rows[0] ?? []);
        return ['rows' => $rows, 'added' => $added, 'skipped' => $skipped];
    }

    private function values(array $axes, array $row): array
    {
        $values = [];
        foreach ($axes as $axis) {
            $option = (int) ($row['axis_' . $axis->id] ?? 0);
            if (!isset($axis->options[$option])) { throw new \RuntimeException('COM_DHARMASHOP_GROUP_COMBINATION_REQUIRED'); }
            $values[(int) $axis->id] = $option;
        }
        return $values;
    }

    /** Validate the whole batch before the first ProductModel::save. */
    public function plan(int $id, array $data): array
    {
        $groups = new GroupService($this->db);
        $group = $groups->get($id);
        if (!$group) { throw new \RuntimeException('COM_DHARMASHOP_GROUP_NOT_FOUND'); }
        if ((int) ($data['revision'] ?? 0) !== (int) $group->revision) { throw new \RuntimeException('COM_DHARMASHOP_GROUP_CHANGED'); }
        $axes = $this->axes(array_map(static fn($a) => (int) $a->id, $groups->axes($id)));
        $taxonomy = (new GroupTaxonomy($this->db))->get($id);
        $catid = (int) ($data['generation_catid'] ?? 0) ?: $taxonomy['catid'];
        $user = \Joomla\CMS\Factory::getApplication()->getIdentity();
        if (!$user->authorise('core.create', 'com_dharmashop.category.' . $catid)
            || !$user->authorise('core.edit', 'com_dharmashop') || !$user->authorise('dharmashop.groups.manage', 'com_dharmashop')) { throw new \RuntimeException('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'); }
        if (!$this->db->setQuery("SELECT id FROM #__categories WHERE id=" . $catid . " AND extension='com_dharmashop'")->loadResult()) {
            throw new \RuntimeException('JLIB_DATABASE_ERROR_CATEGORY_REQUIRED');
        }
        $rows = (array) ($data['executions'] ?? []);
        if (count($rows) > self::LIMIT) { throw new \RuntimeException('COM_DHARMASHOP_GENERATOR_LIMIT'); }
        $existing = [];
        $members = $groups->members($id);
        foreach ($members as $member) { $existing[$member->combo_hash] = true; }
        $plan = $hashes = $skus = $aliases = [];
        $skipped = [];
        $repository = new VariantRepository($this->db, new PriceResolver());
        $productFields = new FieldValues($this->db);
        $copy = (int) $this->db->setQuery('SELECT copy_fields FROM #__dharmashop_product_groups WHERE id=' . $id)->loadResult();
        $common = $copy ? (new FieldValues($this->db, 'group', array_map(static fn($a) => (int) $a->id, $axes)))
            ->values('product', ['id' => $id] + $taxonomy) : [];
        foreach ($rows as $key => $row) {
            $row = (array) $row;
            $values = $this->values($axes, $row);
            $hash = GroupService::hash($values);
            if (isset($existing[$hash])) { $skipped[] = $key; continue; }
            if (isset($hashes[$hash])) { throw new \RuntimeException('COM_DHARMASHOP_GROUP_DUPLICATE'); }
            $hashes[$hash] = true;
            $title = trim((string) ($row['custom_title'] ?? $row['title'] ?? '')) ?: $this->title((string) ($data['title_template'] ?? ''), $group->title, $axes, $row);
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku === '') { $sku = $this->unique('sku', mb_substr($group->alias, 0, 78) . '-' . substr($hash, 0, 12), 100, $skus); }
            if ($title === '' || mb_strlen($title) > 255 || mb_strlen($sku) > 100 || isset($skus[mb_strtolower($sku)])) {
                throw new \RuntimeException('COM_DHARMASHOP_GROUP_GENERATION_REQUIRED');
            }
            $repository->assertSkuAvailable(0, $sku);
            $skus[mb_strtolower($sku)] = true;
            $alias = $this->unique('alias', ApplicationHelper::stringURLSafe($title) ?: 'product', 190, $aliases, $catid);
            $aliases[mb_strtolower($alias)] = true;
            $fields = $markers = [];
            foreach ($values as $field => $value) { $fields['f_' . $field] = $value; $markers['f_' . $field] = 1; }
            $image = ImageUploader::filterPath($row['image'] ?? '');
            if (!empty($row['image']) && $image === '') { throw new \RuntimeException('COM_DHARMASHOP_UPLOAD_ERROR_TYPE'); }
            if ($image && !is_file(JPATH_ROOT . '/' . explode('#', $image, 2)[0])) { throw new \RuntimeException('COM_DHARMASHOP_UPLOAD_ERROR_TYPE'); }
            $state = (int) ($row['product_state'] ?? 1);
            if (!in_array($state, [0, 1], true) || ($state === 1 && !$user->authorise('core.edit.state', 'com_dharmashop'))) {
                throw new \RuntimeException('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN');
            }
            $product = ['id' => 0, 'title' => $title, 'alias' => $alias, 'sku' => $sku, 'state' => $state, 'catid' => $catid,
                'extra_catids' => array_values(array_diff($taxonomy['extra_catids'], [$catid])), 'brand_id' => $taxonomy['brand_id'], 'badge_ids' => $taxonomy['badge_ids'],
                'price' => $row['price'] ?? 0, 'stock_qty' => $row['stock_qty'] ?? 0, 'stock_tracked' => 1,
                'group_id' => $id, 'inherit_intro' => 'group', 'inherit_full' => 'group', 'inherit_images' => $image ? 'own' : 'group',
                'custom_fields' => $fields, 'custom_fields_present' => $markers];
            if ($image) { $product['gallery'] = [['path' => $image, 'alt' => $title]]; }
            foreach (['price', 'stock_qty'] as $number) {
                if ($product[$number] === '') { $product[$number] = 0; }
                if (!is_numeric($product[$number]) || (float) $product[$number] < 0 || !is_finite((float) $product[$number]) || (float) $product[$number] >= ($number === 'price' ? 1e13 : 1e12)) {
                    throw new \RuntimeException('COM_DHARMASHOP_GROUP_GENERATION_REQUIRED');
                }
            }
            $product = $productFields->forCreation($product, $common);
            $prepared = $productFields->prepare('product', $product);
            $groups->validateProduct(0, $id, $prepared);
            $plan[$key] = $product;
        }
        if (count($members) + count($plan) > GroupService::MEMBER_LIMIT) { throw new \RuntimeException('COM_DHARMASHOP_GROUP_LIMIT'); }
        return ['products' => $plan, 'skipped' => $skipped];
    }

    private function unique(string $kind, string $base, int $length, array $reserved, int $catid = 0): string
    {
        $base = mb_substr($base, 0, $length);
        $candidate = $base;
        $suffix = 2;
        $table = $kind === 'sku' ? '#__dharmashop_variants' : '#__dharmashop_products';
        while (isset($reserved[mb_strtolower($candidate)]) || $this->db->setQuery('SELECT id FROM ' . $table . ' WHERE ' . $kind . '=' . $this->db->quote($candidate) . ($kind === 'alias' ? ' AND catid=' . $catid : ''), 0, 1)->loadResult()) {
            $tail = '-' . $suffix++;
            $candidate = mb_substr($base, 0, $length - strlen($tail)) . $tail;
        }
        return $candidate;
    }
}
