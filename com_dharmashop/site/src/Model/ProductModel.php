<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Model;

use Dharma\Component\DharmaShop\Site\Support\MediaUrl;
use Dharma\Component\DharmaShop\Site\Support\SaleCountdown;
use Dharma\Component\DharmaShop\Site\Support\ProductFields;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** Публичная карточка опубликованного товара; закрытая категория скрывает всю ветку товаров. */
class ProductModel extends BaseDatabaseModel
{
    protected $option = 'com_dharmashop';

    public function getItem(): object
    {
        $id = Factory::getApplication()->getInput()->getInt('id');

        if ($id < 1) {
            throw new \RuntimeException(Text::_('COM_DHARMASHOP_PRODUCT_NOT_FOUND'), 404);
        }

        $db = $this->getDatabase();
        $levels = implode(',', array_map('intval', Factory::getApplication()->getIdentity()->getAuthorisedViewLevels()) ?: [0]);
        $blocked = $db->createQuery()->select('blocked.id')->from($db->quoteName('#__categories', 'blocked'))
            ->where('blocked.extension = ' . $db->quote('com_dharmashop'))
            ->where('blocked.lft <= c.lft')->where('blocked.rgt >= c.rgt')
            ->where('(blocked.published != 1 OR blocked.access NOT IN (' . $levels . '))');
        $query = $db->createQuery()->select($db->quoteName([
                'p.id', 'p.catid', 'p.title', 'p.alias', 'p.introtext', 'p.fulltext', 'p.cover', 'p.cover_alt',
                'p.price_min', 'p.price_max', 'p.unit', 'p.qty_min', 'p.qty_step', 'p.in_stock', 'p.metadesc', 'p.metadata', 'p.params',
            ]))
            ->select($db->quoteName('c.title', 'category_title'))
            ->from($db->quoteName('#__dharmashop_products', 'p'))
            ->join('INNER', $db->quoteName('#__categories', 'c') . ' ON c.id = p.catid')
            ->where('p.id = :id')->bind(':id', $id, ParameterType::INTEGER)
            ->where('p.state = 1')->where('c.extension = ' . $db->quote('com_dharmashop'))
            ->where('c.published = 1')->where('c.access IN (' . $levels . ')')
            ->where('NOT EXISTS (' . $blocked . ')');
        $item = $db->setQuery($query)->loadObject();

        if (!$item) {
            throw new \RuntimeException(Text::_('COM_DHARMASHOP_PRODUCT_NOT_FOUND'), 404);
        }

        $item->brand_title = (string) $db->setQuery($db->createQuery()->select('b.title')
            ->from($db->quoteName('#__dharmashop_product_categories', 'pc'))
            ->join('INNER', $db->quoteName('#__categories', 'b') . ' ON b.id = pc.catid')
            ->where('pc.product_id = :brand')->bind(':brand', $id, ParameterType::INTEGER)
            ->where('pc.direct = 1')->where('b.extension = ' . $db->quote('com_dharmashop.brand'))
            ->where('b.published = 1')->where('b.access IN (' . $levels . ')')->order('b.id'), 0, 1)->loadResult();
        $item->description = (string) ($item->fulltext ?? '');
        // Артикул варианта по умолчанию.
        $item->sku = (string) $db->setQuery($db->createQuery()->select('sku')->from($db->quoteName('#__dharmashop_variants'))
            ->where('product_id = :sku')->where('state = 1')->order('is_default DESC, ordering, id')->bind(':sku', $id, ParameterType::INTEGER), 0, 1)->loadResult();
        $item->params = new Registry($item->params);
        $item->metadata = new Registry($item->metadata);
        $priceQuery = $db->createQuery()->select($db->quoteName(['vp.price', 'vp.price_final', 'vp.discount_start', 'vp.discount_end']))
            ->from($db->quoteName('#__dharmashop_variants', 'v'))
            ->join('INNER', $db->quoteName('#__dharmashop_variant_prices', 'vp') . ' ON vp.variant_id = v.id')
            ->where('v.product_id = :product')->bind(':product', $id, ParameterType::INTEGER)
            ->where('v.state = 1')->where('v.is_default = 1')->where('vp.price_list_id = 1');
        $price = $db->setQuery($priceQuery)->loadObject();
        $item->base_price = null;
        $item->discount_amount = null;
        $item->discount_percent = 0.0;
        $item->sale_end_timestamp = null;

        if ($price && $price->price !== null && $price->price_final !== null && (float) $price->price > 0 && (float) $price->price_final < (float) $price->price) {
            $item->base_price = (string) $price->price;
            $item->discount_amount = number_format((float) $price->price - (float) $price->price_final, 2, '.', '');
            $item->discount_percent = (1 - (float) $price->price_final / (float) $price->price) * 100;
            $item->sale_end_timestamp = SaleCountdown::endTimestamp($price->discount_start, $price->discount_end, true);
        }

        // Цепочка категорий от верхней до основной категории товара (для хлебных крошек): id, название для крошки.
        $item->category_path = [];

        foreach ($db->setQuery(
            $db->createQuery()->select($db->quoteName(['a.id', 'a.title', 'a.params']))
                ->from($db->quoteName('#__categories', 'a'))
                ->join('INNER', $db->quoteName('#__categories', 'leaf') . ' ON leaf.id = :leaf AND a.lft <= leaf.lft AND a.rgt >= leaf.rgt')
                ->where('a.extension = ' . $db->quote('com_dharmashop'))->where('a.level >= 1')->order('a.lft')
                ->bind(':leaf', $item->catid, ParameterType::INTEGER)
        )->loadObjectList() as $row) {
            $params = new Registry($row->params);
            $item->category_path[] = (object) ['id' => (int) $row->id, 'title' => (string) ($params->get('breadcrumb_title') ?: $row->title)];
        }

        // Галерея: изображения товара по порядку (без вариантов); при их отсутствии — обложка.
        $item->gallery = [];

        foreach ($db->setQuery(
            $db->createQuery()->select($db->quoteName(['path', 'alt', 'title']))->from($db->quoteName('#__dharmashop_field_media'))
                ->where('product_id = :gallery')->where('field_id = 0')->where('variant_id IS NULL')
                ->bind(':gallery', $id, ParameterType::INTEGER)->order('ordering')
        )->loadObjectList() as $row) {
            if ($url = MediaUrl::absolute((string) $row->path)) {
                $alt = (string) $row->alt;
                $title = (string) $row->title;
                $description = $alt !== '' ? $alt : ($title !== '' ? $title : (string) $item->title);
                $item->gallery[] = (object) ['url' => $url, 'alt' => $description, 'title' => $description];
            }
        }

        if (!$item->gallery && ($url = MediaUrl::absolute((string) $item->cover))) {
            $alt = (string) $item->cover_alt;
            $description = $alt !== '' ? $alt : (string) $item->title;
            $item->gallery[] = (object) ['url' => $url, 'alt' => $description, 'title' => $description];
        }

        ProductFields::attach($db, [$item], 'page');
        return $item;
    }
}
