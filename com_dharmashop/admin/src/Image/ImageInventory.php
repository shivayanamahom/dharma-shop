<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Image;
use Dharma\Component\DharmaShop\Administrator\Support\Sql;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;
\defined('_JEXEC') or die;

/** Bounded keyset scan, including unpublished records and references used only for SEO. */
final class ImageInventory
{
    public function __construct(private readonly DatabaseInterface $db, private readonly Registry $settings) {}

    /** One record per step; cursor is kept server-side. Null means the inventory is exhausted. */
    public function next(array &$cursor): ?array
    {
        $phase = (int) ($cursor['phase'] ?? 0);
        $id = (int) ($cursor['id'] ?? 0);
        $queries = [
            'SELECT id, cover, params FROM #__dharmashop_products WHERE id > :id ORDER BY id',
            "SELECT id, path, field_id, alt, mime FROM #__dharmashop_field_media WHERE (mime IS NULL OR mime LIKE 'application/vnd.dharmashop.video.%') AND id > :id ORDER BY id",
            'SELECT id, path FROM #__dharmashop_category_field_media WHERE mime IS NULL AND id > :id ORDER BY id',
            "SELECT id, extension, params FROM #__categories WHERE extension IN ('com_dharmashop','com_dharmashop.brand','com_dharmashop.badge') AND id > :id ORDER BY id",
            'SELECT id, image FROM #__dharmashop_field_options WHERE id > :id ORDER BY id',
            'SELECT id, path FROM #__dharmashop_group_media WHERE id > :id ORDER BY id',
        ];
        while ($phase < 6) {
            $row = $this->db->setQuery(Sql::query($this->db, $queries[$phase], [':id'=>[$id, ParameterType::INTEGER]]), 0, 1)->loadAssoc();
            if (!$row) { $cursor = ['phase'=>++$phase, 'id'=>0]; $id = 0; continue; }
            $cursor = ['phase'=>$phase, 'id'=>(int)$row['id']];
            $params = json_decode((string)($row['params'] ?? ''), true);
            if (!is_array($params)) $params = [];
            $product = array_values(array_filter(array_keys(ImageContexts::SIZES), static fn($c) => str_starts_with($c,'product.') || str_starts_with($c,'cart.') || str_starts_with($c,'search.') || str_starts_with($c,'module.products.') || str_starts_with($c,'group.teaser.') || in_array($c,['admin.product','email.product'],true)));
            return match ($phase) {
                0 => [[$row['cover'], [...$product, 'seo.social']], [$params['meta_image'] ?? '', ['seo.social']]],
                1 => str_starts_with((string) ($row['mime'] ?? ''), 'application/vnd.dharmashop.video.')
                    ? [[$row['alt'] ?? '', ['field.video.poster']]]
                    : [[$row['path'], (int) $row['field_id'] > 0 ? ['field.gallery.card', 'field.gallery.page', 'field.gallery.lightbox'] : $product]],
                2 => [[$row['path'], ['field.gallery.card', 'field.gallery.page', 'field.gallery.lightbox']]],
                3 => $this->category($params, match ($row['extension']) { 'com_dharmashop.brand'=>'brand', 'com_dharmashop.badge'=>'badge', default=>'category' }),
                4 => [[$row['image'], ['field.option', 'group.choice']]],
                5 => [[$row['path'], [...$product, 'seo.social']]],
            };
        }
        if ($phase === 6) {
            $cursor = ['phase'=>7, 'id'=>0];
            $result = [[$this->settings->get('shop_logo',''), ['email.logo']], [$this->settings->get('seo_default_image',''), ['seo.social']]];
            foreach (['root_category'=>'category', 'root_brand'=>'brand'] as $key=>$kind) {
                $result = array_merge($result, $this->category((array)$this->settings->get($key, []), $kind));
            }
            return $result;
        }
        return null;
    }

    private function category(array $params, string $kind): array
    {
        return [[$params['image'] ?? '', [$kind.'.card', $kind.'.header','admin.category','search.module','seo.social']],
            [$params['icon'] ?? '', ['admin.category']], [$params['meta_image'] ?? '', ['seo.social']]];
    }
}
