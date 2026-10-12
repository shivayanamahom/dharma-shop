<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\View\Search;

use Dharma\Component\DharmaShop\Site\Model\SearchModel;
use Dharma\Component\DharmaShop\Site\Support\CartAssets;
use Dharma\Component\DharmaShop\Site\Support\Taxonomy;
use Dharma\Component\DharmaShop\Site\View\Category\ListingTrait;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

\defined('_JEXEC') or die;

/**
 * Результаты поиска товаров: список карточек как в каталоге (общий `ListingTrait`), сортировка по релевантности по умолчанию, обычная
 * постраничная навигация. Страница не для индексации (`noindex, follow`). Без запроса — только строка поиска.
 */
class HtmlView extends BaseHtmlView
{
    use ListingTrait;

    protected $option = 'com_dharmashop';
    public string $query = '';
    public bool $showHeading = true;
    public array $sortOptions = [];
    public string $searchUrl = '';

    protected function taxonomy(): Taxonomy
    {
        return Taxonomy::search();
    }

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        /** @var SearchModel $model */
        $model = $this->getModel();
        $menu = $this->prepareListing($model, []);
        $this->showHeading = (int) $menu->get('show_page_heading', 1) === 1;
        $this->query = $model->getQueryText();
        $this->searchUrl = \Joomla\CMS\Router\Route::_('index.php?option=com_dharmashop&view=search', false);

        // Сортировка: по релевантности по умолчанию, но если в запросе её нет — настройка списка не подменяет её порядком каталога.
        $requested = $app->getInput()->getCmd('sort', 'relevance');
        $this->ordering = in_array($requested, SearchModel::SORTS, true) ? $requested : 'relevance';
        $this->sortOptions = [
            'relevance' => Text::_('COM_DHARMASHOP_SORT_RELEVANCE'), 'price_asc' => Text::_('COM_DHARMASHOP_SORT_PRICE_ASC'),
            'price_desc' => Text::_('COM_DHARMASHOP_SORT_PRICE_DESC'), 'new' => Text::_('COM_DHARMASHOP_SORT_NEW'),
            'title' => Text::_('COM_DHARMASHOP_SORT_TITLE'),
        ];

        if ($this->query !== '') {
            $this->loadProducts($model);
        }

        $title = $this->query !== '' ? Text::sprintf('COM_DHARMASHOP_SEARCH_TITLE_QUERY', $this->query) : Text::_('COM_DHARMASHOP_SEARCH');
        $document = $this->getDocument();
        $document->setTitle($title);
        $document->setMetaData('robots', 'noindex, follow');
        $app->getPathway()->addItem($this->query !== '' ? $title : Text::_('COM_DHARMASHOP_SEARCH'), '');

        $wa = $document->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_dharmashop');

        if ($this->items) {
            $settings = ComponentHelper::getParams('com_dharmashop');

            if ((int) $this->params->get('product_card_sale_countdown', $settings->get('product_card_sale_countdown', 1)) === 1
                && array_filter($this->items, static fn (object $item): bool => !empty($item->sale_end_timestamp))) {
                $wa->useScript('com_dharmashop.sale-countdown');
            }

            // Несколько фото в карточке — слайдер (как на странице категории).
            if ($this->cardImagesLimit() !== 1) {
                if ($this->sliderType() === 'swiper') {
                    $wa->usePreset('dharmashop.swiper')->useScript('com_dharmashop.card-slider');
                } else {
                    $wa->useScript('bootstrap.carousel');
                }
            }

            // Корзина в карточках (в режиме каталога — без неё).
            if (!$this->catalogMode) {
                CartAssets::use($document);

                if ($this->cartMode() === 'qty') {
                    $wa->useScript('com_dharmashop.qty');
                }
            }
        }

        parent::display($tpl);
    }
}
