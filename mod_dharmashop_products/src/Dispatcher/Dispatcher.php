<?php
declare(strict_types=1);

namespace Dharma\Module\DharmaShopProducts\Site\Dispatcher;

use Dharma\Component\DharmaShop\Administrator\Pricing\MoneyFormatter;
use Dharma\Component\DharmaShop\Site\Support\Grid;
use Dharma\Component\DharmaShop\Site\Support\GroupCardRenderer;
use Dharma\Component\DharmaShop\Site\Support\ProductCardRenderer;
use Dharma\Component\DharmaShop\Site\Support\ProductModuleRegistry;
use Dharma\Module\DharmaShopProducts\Site\Helper\Products;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Log\Log;

\defined('_JEXEC') or die;

final class Dispatcher extends AbstractModuleDispatcher
{
    protected function getLayoutData()
    {
        $data = parent::getLayoutData();
        $data['items'] = [];
        if (!ComponentHelper::isEnabled('com_dharmashop')) { return $data; }
        $app = $this->getApplication();
        $params = $data['params'];
        $source = in_array((string) $params->get('source', 'new'), ['new', 'recent'], true) ? (string) $params->get('source', 'new') : 'new';
        if ($source === 'recent' || (int) $params->get('exclude_duplicates', 0) === 1) { $app->allowCache(false); }
        try {
            $app->bootComponent('com_dharmashop');
            $repository = new Products(Factory::getContainer()->get('DatabaseDriver'), $app);
            $items = $repository->find($params);
        } catch (\Throwable $error) {
            Log::add('Products module: ' . get_class($error), Log::WARNING, 'com_dharmashop');
            return $data;
        }
        if (!$items) { return $data; }
        $data['items'] = $items;
        $data['display'] = in_array((string) $params->get('display', 'grid'), ['grid', 'list', 'slider'], true) ? (string) $params->get('display', 'grid') : 'grid';
        $wa = $app->getDocument()->getWebAssetManager();
        $data['slider'] = false;
        if ($data['display'] === 'slider' && $wa->getRegistry()->exists('preset', 'dharmashop.swiper')) {
            $wa->getRegistry()->addExtensionRegistryFile('mod_dharmashop_products');
            $wa->usePreset('dharmashop.swiper')->useScript('mod_dharmashop_products.slider');
            $data['slider'] = true;
        } elseif ($data['display'] === 'slider') {
            $data['display'] = 'grid';
        }
        $data['gridClass'] = Grid::classes($params, 'products');
        $data['moduleId'] = max(1, (int) $data['module']->id);
        $data['sliderOptions'] = $this->sliderOptions($params);
        $data['sliderTemplate'] = ModuleHelper::getLayoutPath('mod_dharmashop_products', 'slider');

        $componentParams = ComponentHelper::getParams('com_dharmashop');
        $money = new MoneyFormatter($componentParams);
        $products = new ProductCardRenderer($componentParams);
        $groups = new GroupCardRenderer();
        $useComponentLayouts = (int) $params->get('component_layouts', 1) === 1;
        $cardMode = $data['display'] === 'list' ? 'list' : 'grid';
        $context = 'module.products.' . ($data['slider'] ? 'slider' : $cardMode);
        $ownPath = dirname(__DIR__, 2) . '/layouts';
        $override = JPATH_THEMES . '/' . $app->getTemplate() . '/html/layouts/modules/mod_dharmashop_products';
        $productLayout = new FileLayout('product', $ownPath, ['component' => 'none', 'client' => 0]);
        $productLayout->addIncludePath($override);
        $groupLayout = new FileLayout('group', $ownPath, ['component' => 'none', 'client' => 0]);
        $groupLayout->addIncludePath($override);
        $moduleId = (int) $data['moduleId'];
        $data['renderCard'] = static function (object $item) use ($products, $groups, $componentParams, $useComponentLayouts, $cardMode, $context, $productLayout, $groupLayout, $money, $moduleId): string {
            if (GroupCardRenderer::isGroup($item)) {
                if ($useComponentLayouts) { return $groups->render($item, $componentParams, $cardMode, [], null, '', $context); }
                $card = $products->data($item, $cardMode, $context, 'dharma-products-' . $moduleId, true, ['favoriteActiveClass' => 'text-primary']);
                $card['imageContext'] = $context;
                $card['priceRange'] = $money->range($item->listing_group->price_min ?? null, $item->listing_group->price_max ?? null, !empty($item->listing_group->price_free));
                return $groupLayout->render($card + ['item' => $item, 'group' => $item->listing_group, 'mode' => $cardMode,
                    'url' => \Joomla\CMS\Router\Route::_('index.php?option=com_dharmashop&view=product&id=' . (int) $item->id . '&catid=' . (int) $item->catid, false),
                    'imageContext' => $context, 'priceRange' => $money->range($item->listing_group->price_min ?? null, $item->listing_group->price_max ?? null, !empty($item->listing_group->price_free))]);
            }
            if ($useComponentLayouts) {
                $card = $products->data($item, $cardMode, 'module.products', 'dharma-products-' . $moduleId, true, ['favoriteActiveClass' => 'text-primary']);
                if ($context === 'module.products.slider') { $card['imageContext'] = $context; }
                return $products->render($card);
            }
            $card = $products->data($item, $cardMode, 'module.products', 'dharma-products-' . $moduleId, true, ['favoriteActiveClass' => 'text-primary']);
            $card['imageContext'] = $context;
            return $productLayout->render($card + ['item' => $item, 'mode' => $cardMode, 'imageContext' => $context,
                'url' => \Joomla\CMS\Router\Route::_('index.php?option=com_dharmashop&view=product&id=' . (int) $item->id . '&catid=' . (int) $item->catid, false),
                'priceRange' => $money->range($item->price_min, $item->price_max, !empty($item->price_free))]);
        };
        return $data;
    }

    private function sliderOptions(\Joomla\Registry\Registry $params): array
    {
        $columns = [];
        foreach (['xs' => 1, 'sm' => 2, 'md' => 2, 'lg' => 3, 'xl' => 4, 'xxl' => 4] as $key => $default) {
            $columns[$key] = max(1, min(6, (int) $params->get('slider_columns_' . $key, $default)));
        }
        return ['columns' => $columns, 'gap' => max(0, min(64, (int) $params->get('slider_gap', 16))),
            'speed' => max(0, min(3000, (int) $params->get('slider_speed', 400))), 'delay' => max(1000, min(60000, (int) $params->get('slider_delay', 5000))),
            'loop' => (int) $params->get('slider_loop', 0) === 1, 'autoplay' => (int) $params->get('slider_autoplay', 0) === 1,
            'arrows' => (int) $params->get('slider_arrows', 1) === 1, 'pagination' => (int) $params->get('slider_pagination', 1) === 1];
    }
}
