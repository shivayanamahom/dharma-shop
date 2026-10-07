<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\View\Category;

use Dharma\Component\DharmaShop\Administrator\Seo\PlaceholderCatalog;
use Dharma\Component\DharmaShop\Administrator\Support\CategoryTypes;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;

\defined('_JEXEC') or die;

/**
 * Форма категории любого типа.
 */
class HtmlView extends BaseHtmlView
{
    protected $option = 'com_dharmashop';

    protected ?Form $form = null;

    protected ?object $item = null;

    protected string $extension = CategoryTypes::CATEGORY;

    /** @var array{total: int, published: int, in_stock: int} число товаров записи */
    protected array $counts = ['total' => 0, 'published' => 0, 'in_stock' => 0];

    /** Цена товаров записи «от — до» для боковой панели ('—', если цен нет). */
    protected string $priceRange = '—';

    /** Справочник шорткодов: группы с кодами, названиями и текущими значениями этой записи. @var array<int, array{title: string, items: array<int, array{code: string, name: string, value: string}>}> */
    protected array $placeholders = [];

    /** Список полей для штатных блоков joomla.edit.* (задаётся в шаблоне на время вывода бокового блока). */
    public ?array $fields = null;

    public function display($tpl = null): void
    {
        /** @var \Dharma\Component\DharmaShop\Administrator\Model\CategoryModel $model */
        $model = $this->getModel();
        $item = $model->getItem();

        if (!$item) {
            $app = Factory::getApplication();
            $app->enqueueMessage(Text::_('COM_DHARMASHOP_ERROR_CATEGORY_NOT_FOUND'), 'error');
            $app->redirect(Route::_('index.php?option=com_dharmashop&view=categories', false));

            return;
        }

        $this->item = $item;
        $this->extension = $model->getExtension();
        $this->form = $model->getForm();
        $this->counts = $model->getProductCounts((int) $item->id);
        $this->buildPlaceholders($model);

        $this->addToolbar();

        parent::display($tpl);
    }

    /** Цена «от — до» и справочник шорткодов с текущими значениями записи (у новой записи — прочерки). */
    private function buildPlaceholders(object $model): void
    {
        $shop = Factory::getApplication()->bootComponent('com_dharmashop')->getServices();
        $money = $shop->money();
        $id = (int) $this->item->id;
        $range = $model->getPriceRange($id);
        $none = '—';
        $min = $range['min'] !== null ? $money->format($range['min']) : $none;
        $max = $range['max'] !== null ? $money->format($range['max']) : $none;
        $this->priceRange = $range['min'] === null ? $none : ($range['min'] === $range['max'] ? $min : $min . ' — ' . $max);

        $area = $this->extension === CategoryTypes::BRAND ? 'brand' : ($this->extension === CategoryTypes::BADGE ? 'badge' : 'category');
        $values = [
            $area . '.title' => $id > 0 ? (string) $this->item->title : $none,
            'category.parent' => ($parent = $model->getTitleOf((int) ($this->item->parent_id ?? 0))) !== '' ? $parent : $none,
            $area . '.price_min' => $min,
            $area . '.price_max' => $max,
            $area . '.count' => $id > 0 ? (string) $this->counts['published'] : $none,
            'global.sitename' => (string) Factory::getApplication()->get('sitename'),
            'global.currency' => (string) ComponentHelper::getParams('com_dharmashop')->get('currency_symbol', '₽'),
        ];

        $this->placeholders = PlaceholderCatalog::describe($area, $values);
    }

    protected function addToolbar(): void
    {
        Factory::getApplication()->getInput()->set('hidemainmenu', true);

        $isNew = empty($this->item->id);
        $toolbar = Toolbar::getInstance();
        $suffix = match ($this->extension) {
            CategoryTypes::BRAND => 'BRAND',
            CategoryTypes::BADGE => 'BADGE',
            default => 'CATEGORY',
        };

        ToolbarHelper::title(Text::_('COM_DHARMASHOP_' . $suffix . ($isNew ? '_CATEGORY_ADD_TITLE' : '_CATEGORY_EDIT_TITLE')), 'pencil-alt');

        $canCreate = $this->getCurrentUser()->authorise('core.create', 'com_dharmashop');

        $toolbar->apply('category.apply');
        $saveGroup = $toolbar->dropdownButton('save-group');
        $saveGroup->configure(
            function (Toolbar $childBar) use ($canCreate) {
                $childBar->save('category.save');

                if ($canCreate) {
                    $childBar->save2new('category.save2new');
                }
            }
        );
        $toolbar->cancel('category.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');
    }
}
