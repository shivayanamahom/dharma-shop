<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Controller;

use Dharma\Component\DharmaShop\Administrator\Pricing\MoneyFormatter;
use Dharma\Component\DharmaShop\Site\Model\SearchModel;
use Dharma\Component\DharmaShop\Site\Search\ProductSearch;
use Dharma\Component\DharmaShop\Site\Support\MediaUrl;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

\defined('_JEXEC') or die;

/**
 * Подсказки поиска для модуля `mod_dharmashop_search`: `task=search.suggest&format=json&q=…&limit=…`. Только чтение (GET), поэтому токен
 * не нужен; видимость товаров, правила запроса и сортировка — те же, что у страницы результатов (SearchModel). Число подсказок ограничено.
 */
class SearchController extends BaseController
{
    protected $option = 'com_dharmashop';

    /** Предел числа подсказок, что бы ни пришло в запросе. */
    private const MAX_LIMIT = 20;

    public function suggest(): void
    {
        $query = ProductSearch::clean((string) $this->input->get->getString('q', ''));
        $limit = max(1, min(self::MAX_LIMIT, $this->input->get->getInt('limit', 8)));
        $settings = ComponentHelper::getParams('com_dharmashop');
        $money = new MoneyFormatter($settings);
        $items = [];
        $categories = [];
        $total = 0;

        if (mb_strlen($query) >= 2) {
            /** @var SearchModel $model */
            $model = $this->app->bootComponent('com_dharmashop')->getMVCFactory()->createModel('Search', 'Site', ['ignore_request' => true]);
            $total = $model->getTotal();
            // Где найдено: основные категории найденных товаров с числом товаров; ссылка ведёт на страницу категории с тем же запросом.
            foreach ($total ? $model->getCategoryCounts(4) : [] as $row) {
                $categories[] = [
                    'id' => (int) $row->catid,
                    'title' => (string) $row->title,
                    'count' => (int) $row->n,
                    'url' => Route::_('index.php?option=com_dharmashop&view=category&id=' . (int) $row->catid . '&filter[search]=' . rawurlencode($query), false),
                ];
            }

            $products = $total ? $model->getProducts('relevance', $limit, 0) : [];
            $skus = [];

            if ($products) {
                // Артикулы вариантов по умолчанию — одним запросом на все подсказки.
                $db = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
                $ids = implode(',', array_map(static fn (object $product): int => (int) $product->id, $products));
                $skus = $db->setQuery('SELECT product_id, sku FROM ' . $db->quoteName('#__dharmashop_variants') . ' WHERE is_default = 1 AND product_id IN (' . $ids . ')')->loadAssocList('product_id', 'sku');
            }

            foreach ($products as $product) {
                $items[] = [
                    'id' => (int) $product->id,
                    'title' => (string) $product->title,
                    'url' => Route::_('index.php?option=com_dharmashop&view=product&id=' . (int) $product->id . '&catid=' . (int) $product->catid, false),
                    'image' => \Dharma\Component\DharmaShop\Administrator\Image\ImageUrl::resolve((string) $product->cover, 'search.module'),
                    'category' => (string) ($product->category_title ?? ''),
                    'sku' => (string) ($skus[(int) $product->id] ?? ''),
                    'price' => $money->range($product->price_min, $product->price_max),
                    'in_stock' => (int) $product->in_stock === 1,
                ];
            }
        }

        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $this->app->setHeader('Cache-Control', 'no-store', true);
        $this->app->setHeader('X-Robots-Tag', 'noindex', true);
        $this->app->sendHeaders();
        echo json_encode([
            'success' => true,
            'query' => $query,
            'total' => $total,
            'categories' => $categories,
            'items' => $items,
            'url' => Route::_('index.php?option=com_dharmashop&view=search&filter[search]=' . rawurlencode($query), false),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->app->close();
    }
}
