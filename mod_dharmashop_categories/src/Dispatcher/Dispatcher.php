<?php
declare(strict_types=1);

namespace Dharma\Module\DharmaShopCategories\Site\Dispatcher;

use Dharma\Component\DharmaShop\Site\Support\Grid;
use Dharma\Component\DharmaShop\Administrator\Pricing\MoneyFormatter;
use Dharma\Module\DharmaShopCategories\Site\Helper\Categories;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Router\Route;

\defined('_JEXEC') or die;

class Dispatcher extends AbstractModuleDispatcher
{
    protected function getLayoutData()
    {
        $data = parent::getLayoutData();
        $data['items'] = [];
        $data['showAll'] = false;
        if (!ComponentHelper::isEnabled('com_dharmashop')) { return $data; }
        $app = $this->getApplication();
        $params = $data['params'];
        $data['display'] = in_array($params->get('display'), ['accordion', 'slider'], true) ? $params->get('display') : 'grid';
        $app->bootComponent('com_dharmashop');
        $showCount = (int) $params->get('show_count', 0) === 1;
        $showPrices = (int) $params->get('show_prices', 0) === 1;
        try {
            $repository = new Categories(Factory::getContainer()->get('DatabaseDriver'));
            $result = $repository->find($params, $app->getIdentity()->getAuthorisedViewLevels());
            $data['children'] = $data['display'] === 'accordion'
                ? $repository->children($result['items'], $params, $app->getIdentity()->getAuthorisedViewLevels()) : [];
            $cards = $result['items'];
            if ($data['display'] === 'accordion') {
                $cards = [];
                foreach ($data['children'] as $group) { $cards = array_merge($cards, $group['items']); }
            }
            $cards = array_column($cards, null, 'id');
            $stats = $repository->stats(array_values($cards), $showCount, $showPrices);
        } catch (\Throwable $error) {
            Log::add('Categories module: ' . get_class($error), Log::WARNING, 'com_dharmashop');
            return $data;
        }
        $data['items'] = $result['items'];
        if (!$data['items']) { return $data; }
        $settings = ComponentHelper::getParams('com_dharmashop');
        $money = new MoneyFormatter($settings);
        $app->getLanguage()->load('com_dharmashop', JPATH_SITE);
        $router = $app->getRouter()->getComponentRouter('com_dharmashop');
        if (method_exists($router, 'primeCategories')) { $router->primeCategories(array_merge($result['routes'], array_values($cards))); }
        foreach (array_column(array_merge($data['items'], array_values($cards)), null, 'id') as $item) {
            $item->url = Route::_('index.php?option=com_dharmashop&view=category&id=' . (int) $item->id, false);
            $stat = $stats[(int) $item->id] ?? null;
            $item->productCount = (int) ($stat->total ?? 0) > 10000 ? '10000+' : (string) ($stat->total ?? '0');
            $item->priceRange = isset($stat->pmin) ? $money->format((string) $stat->pmin) : '';
            if (isset($stat->pmin, $stat->pmax) && (string) $stat->pmin !== (string) $stat->pmax) {
                $item->priceRange .= ' — ' . $money->format((string) $stat->pmax);
            }
        }
        $data['gridClass'] = Grid::classes($params, 'categories');
        $data['accordionId'] = 'dharma-categories-' . (int) $data['module']->id;
        $data['multipleOpen'] = (int) $params->get('multiple_open', 0) === 1;
        $data['openFirst'] = (int) $params->get('open_first', 0) === 1;
        if ($data['display'] === 'accordion' && $cards) {
            $app->getDocument()->getWebAssetManager()->useScript('bootstrap.collapse');
        }
        $wa = $app->getDocument()->getWebAssetManager();
        if ($data['display'] === 'grid') {
            $wa->getRegistry()->addExtensionRegistryFile('mod_dharmashop_categories');
            $wa->useStyle('mod_dharmashop_categories.grid');
        }
        $data['slider'] = false;
        if ($data['display'] === 'slider' && $wa->getRegistry()->exists('preset', 'dharmashop.swiper')) {
            $wa->getRegistry()->addExtensionRegistryFile('mod_dharmashop_categories');
            $wa->usePreset('dharmashop.swiper')->useScript('mod_dharmashop_categories.slider');
            $data['slider'] = true;
        }
        $data['sliderTemplate'] = ModuleHelper::getLayoutPath('mod_dharmashop_categories', 'slider');
        $data['sliderOptions'] = $this->sliderOptions($params);
        $context = 'module.categories.' . $data['display'];
        $ownLayouts = dirname(__DIR__, 2) . '/layouts';
        $override = JPATH_THEMES . '/' . $app->getTemplate() . '/html/layouts/modules/mod_dharmashop_categories';
        $statsLayout = new FileLayout('stats', $ownLayouts, ['component' => 'none', 'client' => 0]);
        $statsLayout->addIncludePath($override);
        $headingLayout = new FileLayout('accordion-heading', $ownLayouts, ['component' => 'none', 'client' => 0]);
        $headingLayout->addIncludePath($override);
        $data['renderHeading'] = static fn (object $item): string => $headingLayout->render([
            'item' => $item, 'showImage' => (int) $params->get('show_image', 1) === 1,
            'imageContext' => 'module.categories.accordion',
        ]);
        if ($data['display'] !== 'accordion' && (int) $params->get('component_layouts', 1) === 1) {
            $name = (string) $settings->get('category_card_layout', 'default');
            if (!preg_match('/^[a-zA-Z0-9_-]+$/D', $name)) { $name = 'default'; }
            $relative = 'layouts/components/dharmashop/category-card/' . $name . '.php';
            if (!is_file(JPATH_SITE . '/' . $relative) && !is_file(JPATH_THEMES . '/' . $app->getTemplate() . '/html/' . $relative)) { $name = 'default'; }
            $layout = new FileLayout('components.dharmashop.category-card.' . $name, null, ['component' => 'none', 'client' => 0]);
        } else {
            $layout = new FileLayout($data['display'], $ownLayouts, ['component' => 'none', 'client' => 0]);
            $layout->addIncludePath($override);
        }
        $data['renderCard'] = static fn (object $item): string => $layout->render([
            'item' => $item, 'url' => $item->url, 'imageContext' => $context,
            'showImage' => (int) $params->get('show_image', 1) === 1,
            'showIntro' => (int) $params->get('show_intro', 1) === 1,
            'showCount' => $showCount, 'showPrices' => $showPrices, 'fields' => [],
            'productCount' => $item->productCount, 'priceRange' => $item->priceRange,
            'renderStats' => static fn (array $values): string => $statsLayout->render($values),
        ]);
        $data['showAll'] = $result['more'] && (int) $params->get('show_all', 1) === 1;
        $data['allUrl'] = $data['showAll'] ? $this->catalogUrl((int) $params->get('catalog_menu', 0)) : '';
        $data['buttonText'] = trim((string) $params->get('button_text', '')) ?: Text::_('MOD_DHARMASHOP_CATEGORIES_ALL');
        $classes = trim((string) preg_replace('/[^A-Za-z0-9_\- ]/', '', (string) $params->get('button_class', 'btn btn-outline-primary')));
        $data['buttonClass'] = $classes ?: 'btn btn-outline-primary';
        return $data;
    }

    private function sliderOptions(\Joomla\Registry\Registry $params): array
    {
        $options = ['columns' => []];
        foreach (['xs' => 1, 'sm' => 2, 'md' => 2, 'lg' => 3, 'xl' => 4, 'xxl' => 4] as $size => $default) {
            $options['columns'][$size] = max(1, min(6, (int) $params->get('slider_columns_' . $size, $default)));
        }
        foreach (['gap' => [16, 0, 64], 'speed' => [400, 0, 3000], 'delay' => [5000, 1000, 60000]] as $key => [$default, $min, $max]) {
            $options[$key] = max($min, min($max, (int) $params->get('slider_' . $key, $default)));
        }
        foreach (['loop' => 0, 'autoplay' => 0, 'arrows' => 1, 'pagination' => 1] as $key => $default) {
            $options[$key] = (int) $params->get('slider_' . $key, $default) === 1;
        }
        return $options;
    }

    /** Auto-detect an accessible catalog root menu; Joomla still builds a valid URL without one. */
    private function catalogUrl(int $menuId): string
    {
        $app = $this->getApplication();
        $levels = $app->getIdentity()->getAuthorisedViewLevels();
        $language = $app->getLanguage()->getTag();
        $chosen = null;
        foreach ($app->getMenu()->getMenu() as $item) {
            if (!in_array((int) $item->access, $levels, true) || !in_array($item->language, ['*', $language], true)) { continue; }
            if ($menuId > 0 && (int) $item->id === $menuId) { $chosen = $item; break; }
            $query = $item->query;
            if ($menuId <= 0 && ($query['option'] ?? '') === 'com_dharmashop'
                && in_array($query['view'] ?? '', ['categories', 'category'], true) && (int) ($query['id'] ?? 0) <= 1) {
                $chosen = $item;
                if (($query['view'] ?? '') === 'category') { break; }
            }
        }
        return Route::_($chosen ? 'index.php?Itemid=' . (int) $chosen->id : 'index.php?option=com_dharmashop&view=category&id=0', false);
    }
}
