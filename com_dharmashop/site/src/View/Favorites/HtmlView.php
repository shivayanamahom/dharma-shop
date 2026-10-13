<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\View\Favorites;

use Dharma\Component\DharmaShop\Site\Support\FavoritesSession;
use Dharma\Component\DharmaShop\Site\Support\FavoritesSettings;
use Dharma\Component\DharmaShop\Site\Support\ProductCardRenderer;
use Dharma\Component\DharmaShop\Site\Placeholder\GlobalValues;
use Dharma\Component\DharmaShop\Site\Placeholder\Renderer;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

class HtmlView extends BaseHtmlView
{
    protected $option = 'com_dharmashop';
    public array $items = [];
    public string $clearUrl = '';
    public int $count = 0;
    public int $columns = 3;
    public string $gridClasses = '';
    public string $cardLayout = 'grid';
    public string $cardLayoutSource = 'component';
    public string $pageHeading = '';
    public string $introText = '';
    public string $fullText = '';
    public bool $showHeading = true;
    public string $favoritesTokenName = '';
    public ?ProductCardRenderer $cardRenderer = null;

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        $app->allowCache(false);
        $settings = ComponentHelper::getParams('com_dharmashop');
        $activeMenu = $app->getMenu()->getActive();
        $menuParams = $activeMenu && $activeMenu->component === 'com_dharmashop' && ($activeMenu->query['view'] ?? '') === 'favorites'
            ? $activeMenu->getParams() : new Registry();
        $this->cardLayout = $this->menuValue($menuParams, 'favorites_card_layout', FavoritesSettings::cardLayout());
        if (!in_array($this->cardLayout, ['grid', 'list', 'table'], true)) {
            $this->cardLayout = FavoritesSettings::cardLayout();
        }
        $this->cardLayoutSource = $this->menuValue($menuParams, 'favorites_card_layout_source', FavoritesSettings::cardLayoutSource());
        if (!in_array($this->cardLayoutSource, ['component', 'override'], true)) {
            $this->cardLayoutSource = FavoritesSettings::cardLayoutSource();
        }
        $columns = $menuParams->get('favorites_columns', '');
        $this->columns = max(1, min(6, (int) ($columns === '' ? FavoritesSettings::columns() : $columns)));
        $this->gridClasses = FavoritesSettings::gridClasses($menuParams);
        $heading = trim((string) $menuParams->get('favorites_page_heading', ''));
        $this->pageHeading = $heading !== '' ? $heading : Text::_('COM_DHARMASHOP_FAVORITES_TITLE');
        $showHeading = $menuParams->get('favorites_show_heading', '');
        $this->showHeading = $showHeading === '' || (int) $showHeading === 1;
        $this->introText = (string) $menuParams->get('favorites_introtext', '');
        $this->fullText = (string) $menuParams->get('favorites_fulltext', '');
        $this->favoritesTokenName = Session::getFormToken();
        $session = new FavoritesSession($app->bootComponent('com_dharmashop')->getServices()->favorites(), $app);
        if ($list = $session->current()) {
            $this->count = $session->service()->count((int) $list->id);
            $this->items = $session->service()->products((int) $list->id);
            foreach ($this->items as $item) {
                $item->params = new Registry($item->params ?? '');
                $item->price_free = $app->bootComponent('com_dharmashop')->getServices()->orderRules()->priceIsFree($item);
                $item->images = [];
                $item->fields = [];
                $item->badges = [];
                $item->base_price = null;
                $item->discount_amount = null;
                $item->discount_percent = 0.0;
                $item->sale_end_timestamp = null;
                $item->url = Route::_('index.php?option=com_dharmashop&view=product&id=' . (int) $item->id . '&catid=' . (int) $item->catid, false);
            }

            if ($this->items) {
                $component = $app->bootComponent('com_dharmashop');
                $model = $component->getMVCFactory()->createModel('Category', 'Site', ['ignore_request' => true]);
                // Сколько фото в карточке — та же настройка, что у каталога («Фото в карточке»): при нескольких фото выводится слайдер.
                $images = (string) $settings->get('product_card_images', '1');
                $model->attachCardData($this->items, in_array($images, ['0', '1', '2', '3', '4', '5'], true) ? (int) $images : 1);
            }
        }
        $this->clearUrl = Route::_('index.php?option=com_dharmashop&task=favorites.clear&format=json', false);
        $document = $this->getDocument();
        if ($document instanceof HtmlDocument) {
            $document->getWebAssetManager()->getRegistry()->addExtensionRegistryFile('com_dharmashop');
            $document->getWebAssetManager()->useScript('com_dharmashop.favorites');
            $document->addScriptOptions('com_dharmashop.favorites', [
                'toggleUrl' => Route::_('index.php?option=com_dharmashop&task=favorites.toggle&format=json', false),
                'clearUrl' => $this->clearUrl,
                'tokenName' => $this->favoritesTokenName,
                'error' => Text::_('COM_DHARMASHOP_FAVORITES_ERROR_GENERIC'),
                'removedMessage' => Text::_('COM_DHARMASHOP_FAVORITES_REMOVED'),
            ]);
            $cardParams = clone $settings;
            $cardParams->set('product_card_intro', 0);
            $this->cardRenderer = new ProductCardRenderer($cardParams);
            $document->setTitle($this->pageHeading);
            $document->setMetaData('robots', 'noindex, nofollow');
        }
        parent::display($tpl);
    }

    private function menuValue(Registry $menuParams, string $key, string $fallback): string
    {
        $value = trim((string) $menuParams->get($key, ''));

        return $value !== '' ? $value : $fallback;
    }

    public function text(string $html): string
    {
        return (new Renderer())->renderHtml($html, GlobalValues::build(ComponentHelper::getParams('com_dharmashop')));
    }
}
