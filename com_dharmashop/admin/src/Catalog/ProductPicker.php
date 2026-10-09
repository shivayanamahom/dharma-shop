<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Catalog;

use Dharma\Component\DharmaShop\Administrator\Image\ImageUrl;
use Dharma\Component\DharmaShop\Administrator\Pricing\Discount;
use Dharma\Component\DharmaShop\Administrator\Pricing\MoneyFormatter;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/** Read-only picker presentation. Prices follow the existing administrator product list. */
final class ProductPicker
{
    /** Per-request cache shared by repeated fields, separated by database connection. */
    private static array $rows = [];
    private MoneyFormatter $money;

    public function __construct(private readonly DatabaseInterface $db)
    {
        $this->money = new MoneyFormatter(ComponentHelper::getParams('com_dharmashop'));
    }

    public function selected(int $id, array $formData): ?object
    {
        if ($id < 1) { return null; }
        $this->preload($formData, $id);
        return self::$rows[spl_object_id($this->db)][$id] ?? null;
    }

    /** Call with the root form: native subform row Forms contain only their own row. */
    public function preload(array $formData, int $currentId = 0): void
    {
        $connection = spl_object_id($this->db);
        $ids = $currentId > 0 ? [$currentId => $currentId] : [];
        $collect = static function (array $data) use (&$collect, &$ids): void {
            foreach ($data as $key => $value) {
                if (count($ids) >= 500) { return; }
                if ($key === 'product_id' && is_scalar($value) && (int) $value > 0) { $ids[(int) $value] = (int) $value; }
                elseif (is_array($value)) { $collect($value); }
            }
        };
        $collect($formData);
        $missing = array_diff_key($ids, self::$rows[$connection] ?? []);
        if ($missing) {
            foreach ($missing as $value) { self::$rows[$connection][$value] = null; }
            $query = $this->db->createQuery()->select('p.id,p.title,p.cover,p.price_min,p.price_max,vp.price AS price_regular,vp.price_final,vp.discount,vp.discount_percent')
                ->from('#__dharmashop_products p')
                ->join('LEFT', '#__dharmashop_variants v ON v.product_id=p.id AND v.is_default=1')
                ->join('LEFT', '#__dharmashop_variant_prices vp ON vp.variant_id=v.id AND vp.price_list_id=1')
                ->whereIn('p.id', array_values($missing));
            foreach ($this->db->setQuery($query)->loadObjectList() as $row) { self::$rows[$connection][(int) $row->id] = $row; }
        }
    }

    /** Plain strings only: shared by server-rendered previews and the native selection message. */
    public function presentation(object $item, string $context): array
    {
        $sale = $item->price_min !== null && $item->price_final !== null && $item->price_regular !== null
            && (float) $item->price_final < (float) $item->price_regular && $item->discount !== null;
        $price = $item->price_min === null ? Text::_('COM_DHARMASHOP_PRICE_ON_REQUEST')
            : ($sale ? $this->money->format($item->price_final) : $this->money->range($item->price_min, $item->price_max));
        $image = trim((string) ($item->cover ?? ''));
        return [
            'id' => (int) $item->id, 'title' => (string) $item->title,
            'image' => $image !== '' ? ImageUrl::resolve($image, $context) : '',
            'price' => $price,
            'regular' => $sale ? $this->money->format($item->price_regular) : '',
            'discount' => $sale ? '−' . ((int) $item->discount_percent === 1 ? Discount::format($item->discount, 1) : $this->money->format($item->discount)) : '',
        ];
    }
}
