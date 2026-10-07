<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Model;

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
                'p.price_min', 'p.price_max', 'p.unit', 'p.in_stock', 'p.metadesc', 'p.metadata', 'p.params',
            ]))
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

        $item->description = (string) ($item->fulltext ?? '');
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

        ProductFields::attach($db, [$item], 'page');
        return $item;
    }
}
