<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Catalog;

use Dharma\Component\DharmaShop\Administrator\Image\ImageUrl;
use Dharma\Component\DharmaShop\Administrator\Pricing\Discount;
use Dharma\Component\DharmaShop\Administrator\Pricing\MoneyFormatter;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/** Read-only picker presentation. Prices follow the existing administrator product list. */
final class ProductPicker
{
    /** Per-request cache shared by repeated fields, separated by database connection. */
    private static array $rows = [];
    private static array $memberChoices = [];
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
    public function preload(array $formData, int $currentId = 0, bool $withChoices = false): void
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
            $query = $this->db->createQuery()->select('p.id,p.title,p.cover,p.price_min,p.price_max,p.state,p.in_stock,COALESCE(v.stock_tracked,p.stock_tracked,0) AS stock_tracked,v.stock_total AS stock_qty,vp.price AS price_regular,vp.price_final,vp.discount,vp.discount_percent')
                ->from('#__dharmashop_products p')
                ->join('LEFT', '#__dharmashop_variants v ON v.product_id=p.id AND v.is_default=1')
                ->join('LEFT', '#__dharmashop_variant_prices vp ON vp.variant_id=v.id AND vp.price_list_id=1')
                ->whereIn('p.id', array_values($missing));
            foreach ($this->db->setQuery($query)->loadObjectList() as $row) { self::$rows[$connection][(int) $row->id] = $row; }
        }
        if ($withChoices) { $this->preloadChoices(array_values($ids)); }
    }

    /** One bounded batch for a group form or a modal page; ordinary pickers do not load axes. */
    public function preloadChoices(array $ids): void
    {
        $connection = spl_object_id($this->db);
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $ids), static fn($id) => $id > 0))), 0, 500);
        $missing = array_diff($ids, array_keys(self::$memberChoices[$connection] ?? []));
        if (!$missing) { return; }
        foreach ($missing as $id) { self::$memberChoices[$connection][$id] = []; }
        $query = $this->db->createQuery()->select('fv.product_id,f.id,f.params,o.title,o.color,o.image')
            ->from('#__dharmashop_field_values fv')
            ->join('INNER', '#__dharmashop_fields f ON f.id=fv.field_id')
            ->join('INNER', '#__dharmashop_field_options o ON o.id=fv.option_id AND o.field_id=f.id')
            ->whereIn('fv.product_id', array_values($missing))->where('fv.variant_id IS NULL')
            ->where("f.target='product' AND f.state=1 AND f.level=1 AND f.store='option' AND f.type IN ('list','color')")
            ->order('f.ordering,f.id,o.ordering,o.id');
        foreach ($this->db->setQuery($query)->loadObjectList() as $row) {
            $params = new Registry($row->params);
            if (!(int) $params->get('variation', 0) || (int) $params->get('list_multiple', $params->get('multiple', 0))) { continue; }
            $color = preg_match('/^#[0-9a-f]{6}$/iD', (string) $row->color) ? (string) $row->color : '';
            self::$memberChoices[$connection][(int) $row->product_id][(int) $row->id][] = [
                'title' => (string) $row->title, 'color' => $color,
                'image' => $color === '' && trim((string) $row->image) !== ''
                    ? ImageUrl::resolve((string) $row->image, 'admin.picker.member.choice') : '',
            ];
        }
    }

    public function choices(int $id): array
    {
        $this->preloadChoices([$id]);
        return self::$memberChoices[spl_object_id($this->db)][$id] ?? [];
    }

    /** Plain strings only: shared by server-rendered previews and the native selection message. */
    public function presentation(object $item, string $context): array
    {
        $sale = $item->price_min !== null && $item->price_final !== null && $item->price_regular !== null
            && (float) $item->price_final < (float) $item->price_regular && $item->discount !== null;
        $price = $item->price_min === null ? Text::_('COM_DHARMASHOP_PRICE_ON_REQUEST')
            : ($sale ? $this->money->format($item->price_final) : $this->money->range($item->price_min, $item->price_max));
        $image = trim((string) ($item->cover ?? ''));
        $stock = (int) ($item->in_stock ?? 0) === 1 ? Text::_('COM_DHARMASHOP_IN_STOCK') : Text::_('COM_DHARMASHOP_OUT_OF_STOCK');
        if ((int) ($item->in_stock ?? 0) === 1 && (int) ($item->stock_tracked ?? 0) === 1 && $item->stock_qty !== null) {
            $quantity = rtrim(rtrim(number_format((float) $item->stock_qty, 3, '.', ' '), '0'), '.');
            $stock .= ': ' . $quantity;
        }
        $state = match ((int) ($item->state ?? 0)) {
            1 => Text::_('JPUBLISHED'),
            0 => Text::_('JUNPUBLISHED'),
            2 => Text::_('JARCHIVED'),
            -2 => Text::_('JTRASHED'),
            default => Text::_('JSTATUS'),
        };
        return [
            'id' => (int) $item->id, 'title' => (string) $item->title,
            'image' => $image !== '' ? ImageUrl::resolve($image, $context) : '',
            'editUrl' => Route::_('index.php?option=com_dharmashop&task=product.edit&id=' . (int) $item->id),
            'price' => $price,
            'stock' => $stock, 'state' => $state,
            'regular' => $sale ? $this->money->format($item->price_regular) : '',
            'discount' => $sale ? '−' . ((int) $item->discount_percent === 1 ? Discount::format($item->discount, 1) : $this->money->format($item->discount)) : '',
        ];
    }
}
