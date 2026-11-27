<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Controller;

use Dharma\Component\DharmaShop\Administrator\Filter\FilterCache;
use Dharma\Component\DharmaShop\Administrator\Image\ImageUrl;
use Dharma\Component\DharmaShop\Administrator\Pricing\MoneyFormatter;
use Dharma\Component\DharmaShop\Site\Model\SearchModel;
use Dharma\Component\DharmaShop\Site\Search\ProductSearch;
use Dharma\Component\DharmaShop\Site\Service\Router;
use Dharma\Component\DharmaShop\Site\Support\RateLimiter;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

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

    /** Лимит подсказок с одного IP за окно (сек) и длительность окна. */
    private const RATE_LIMIT = 90;

    private const RATE_WINDOW = 60;

    public function suggest(): void
    {
        $query = ProductSearch::clean((string) $this->input->get->getString('q', ''));
        $limit = max(1, min(self::MAX_LIMIT, $this->input->get->getInt('limit', 8)));

        if (!RateLimiter::allow('search-suggest', RateLimiter::clientAddress(), self::RATE_LIMIT, self::RATE_WINDOW)) {
            $this->app->setHeader('status', '429', true);
            $this->app->setHeader('Retry-After', (string) self::RATE_WINDOW, true);
            $this->sendJson([
                'success' => false,
                'query' => $query,
                'total' => 0,
                'categories' => [],
                'items' => [],
                'url' => '',
            ]);

            return;
        }

        $settings = ComponentHelper::getParams('com_dharmashop');

        if (mb_strlen($query) < 2) {
            $this->sendJson([
                'success' => true,
                'query' => $query,
                'total' => 0,
                'categories' => [],
                'items' => [],
                'url' => Route::_('index.php?option=com_dharmashop&view=search&filter[search]=' . rawurlencode($query), false),
            ]);

            return;
        }

        // Кэшируем только короткие частые префиксы (2-3 символа); точный регистр сохраняет совпадение ссылок («SAM» vs «sam»).
        $cacheable = mb_strlen($query) <= 3;
        $user = $this->app->getIdentity();
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $key = 'suggest:v3:' . md5(json_encode([
            $db->getPrefix(),
            $query,
            $limit,
            $this->app->getLanguage()->getTag(),
            $this->input->getInt('Itemid'),
            $user->getAuthorisedViewLevels(),
            $user->getAuthorisedGroups(),
            md5($settings->toString()),
        ]));

        $payload = FilterCache::remember($key, fn (): array => $this->buildSuggest($query, $limit, $settings), $cacheable);
        $this->sendJson(['success' => true, 'query' => $query] + $payload);
    }

    /** @return array{total: int, categories: array, items: array, url: string} */
    private function buildSuggest(string $query, int $limit, Registry $settings): array
    {
        $money = new MoneyFormatter($settings);
        $items = [];
        $categories = [];

        /** @var SearchModel $model */
        $model = $this->app->bootComponent('com_dharmashop')->getMVCFactory()->createModel('Search', 'Site', ['ignore_request' => true]);
        $model->forCategory(0)->forSearch($query);
        $total = $model->getTotal();
        $catRows = $total ? $model->getCategoryCounts(4) : [];

        if ($catRows) {
            $router = method_exists($this->app, 'getRouter') ? $this->app->getRouter()->getComponentRouter('com_dharmashop') : null;

            if ($router && method_exists($router, 'primeCategoryIds')) {
                $router->primeCategoryIds(array_map(static fn (object $row): int => (int) $row->catid, $catRows));
            }

            // Где найдено: основные категории найденных товаров с числом товаров; ссылка ведёт на страницу категории с тем же запросом.
            foreach ($catRows as $row) {
                $categories[] = [
                    'id' => (int) $row->catid,
                    'title' => (string) $row->title,
                    'count' => (int) $row->n,
                    'url' => Route::_('index.php?option=com_dharmashop&view=category&id=' . (int) $row->catid . '&filter[search]=' . rawurlencode($query), false),
                ];
            }
        }

        $products = $total ? $model->getProducts('relevance', $limit, 0) : [];
        $skus = [];

        if ($products) {
            Router::prime($products);
            // Артикулы вариантов по умолчанию — одним запросом на все подсказки.
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $ids = implode(',', array_map(static fn (object $product): int => (int) $product->id, $products));
            $skus = $db->setQuery('SELECT product_id, sku FROM ' . $db->quoteName('#__dharmashop_variants') . ' WHERE is_default = 1 AND product_id IN (' . $ids . ')')->loadAssocList('product_id', 'sku');
        }

        foreach ($products as $product) {
            $items[] = [
                'id' => (int) $product->id,
                'title' => (string) $product->title,
                'url' => Route::_('index.php?option=com_dharmashop&view=product&id=' . (int) $product->id . '&catid=' . (int) $product->catid, false),
                'image' => ImageUrl::resolve((string) $product->cover, 'search.module'),
                'category' => (string) ($product->category_title ?? ''),
                'sku' => (string) ($skus[(int) $product->id] ?? ''),
                'price' => $money->range($product->price_min, $product->price_max, !empty($product->price_free)),
                'in_stock' => (int) $product->in_stock === 1,
            ];
        }

        return [
            'total' => $total,
            'categories' => $categories,
            'items' => $items,
            'url' => Route::_('index.php?option=com_dharmashop&view=search&filter[search]=' . rawurlencode($query), false),
        ];
    }

    private function sendJson(array $payload): void
    {
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $this->app->setHeader('X-Content-Type-Options', 'nosniff', true);
        $this->app->setHeader('Cache-Control', 'no-store', true);
        $this->app->setHeader('X-Robots-Tag', 'noindex', true);
        $this->app->sendHeaders();
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->app->close();
    }
}
