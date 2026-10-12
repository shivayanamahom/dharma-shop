<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\View\Categories;

use Dharma\Component\DharmaShop\Administrator\Support\CategoryTypes;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;

\defined('_JEXEC') or die;

/**
 * Список категорий одного типа (категории, бренды или бейджи).
 */
class HtmlView extends BaseHtmlView
{
    protected $option = 'com_dharmashop';

    /** @var object[] */
    protected array $items = [];

    protected ?Pagination $pagination = null;

    public ?Form $filterForm = null;

    public array $activeFilters = [];

    /** Форма окна «Пакетная обработка» (forms/batch_category.xml без блоков, лишних для типа). */
    public ?Form $batchForm = null;

    protected string $extension = CategoryTypes::CATEGORY;

    /** @var array<int, int[]> родитель => дочерние записи на странице (для перетаскивания вложенных) */
    protected array $ordering = [];

    public function display($tpl = null): void
    {
        /** @var \Dharma\Component\DharmaShop\Administrator\Model\CategoriesModel $model */
        $model = $this->getModel();

        $this->extension = $model->getExtension();
        $this->items = $model->getItems();
        $this->pagination = $model->getPagination();
        $this->filterForm = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();

        if ($errors = $model->getErrors()) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        $form = Form::getInstance('com_dharmashop.batch.category.' . $this->extension, JPATH_ADMINISTRATOR . '/components/com_dharmashop/forms/batch_category.xml', ['control' => '']);
        $form->setFieldAttribute('parent_id', 'extension', $this->extension, 'batch.parent');
        $form->setFieldAttribute('show_in_list', 'label', $this->extension === CategoryTypes::BRAND ? 'COM_DHARMASHOP_CAT_SHOW_IN_LIST_BRAND_LABEL' : 'COM_DHARMASHOP_CAT_SHOW_IN_LIST_CATEGORY_LABEL', 'batch.display');

        if ($this->extension === CategoryTypes::BADGE) {
            // Бейдж плоский и без страницы: остаётся только доступ.
            $form->removeField('parent_id', 'batch.parent');
            $form->removeField('show_in_list', 'batch.display');
            $form->removeField('list_ordering', 'batch.products');
            $form->removeField('list_limit', 'batch.products');
        }

        $this->batchForm = $form;

        foreach ($this->items as $item) {
            $this->ordering[$item->parent_id][] = $item->id;
        }

        $this->addToolbar();

        parent::display($tpl);
    }

    protected function addToolbar(): void
    {
        $canDo = ContentHelper::getActions('com_dharmashop');
        $toolbar = Toolbar::getInstance();
        $state = $this->getModel()->getState();
        $suffix = match ($this->extension) {
            CategoryTypes::BRAND => 'BRANDS',
            CategoryTypes::BADGE => 'BADGES',
            default => 'CATEGORIES',
        };

        ToolbarHelper::title(Text::_('COM_DHARMASHOP_' . $suffix . '_TITLE'), 'folder');

        if ($canDo->get('core.create')) {
            $toolbar->addNew('category.add');
        }

        if ($canDo->get('core.edit.state') || $canDo->get('core.delete')) {
            $dropdown = $toolbar->dropdownButton('status-group')
                ->toggleSplit(false)
                ->text('COM_DHARMASHOP_SELECTED_ACTIONS')
                ->icon('icon-ellipsis-h')
                ->buttonClass('btn btn-action')
                ->listCheck(true);

            $childBar = $dropdown->getChildToolbar();

            if ($canDo->get('core.edit.state')) {
                $childBar->publish('categories.publish')->listCheck(true);
                $childBar->unpublish('categories.unpublish')->listCheck(true);
                $childBar->archive('categories.archive')->listCheck(true);
                $childBar->checkin('categories.checkin');

                if ((string) $state->get('filter.published') !== '-2') {
                    $childBar->trash('categories.trash')->listCheck(true);
                }
            }

            // Пакетная обработка (состав — tmpl/categories/default_batch_body.php и CategoryModel::$batch_commands).
            if ($canDo->get('core.create') && $canDo->get('core.edit')) {
                $childBar->popupButton('batch', 'JTOOLBAR_BATCH')
                    ->popupType('inline')
                    ->textHeader(Text::_('COM_DHARMASHOP_BATCH_OPTIONS_CATEGORIES'))
                    ->url('#joomla-dialog-batch')
                    ->modalWidth('1200px')
                    ->modalHeight('fit-content')
                    ->listCheck(true);
            }
        }

        if ($state->get('filter.published') == -2 && $canDo->get('core.delete')) {
            $toolbar->delete('categories.delete', 'JTOOLBAR_EMPTY_TRASH')->message('JGLOBAL_CONFIRM_DELETE')->listCheck(true);
        }

        if ($canDo->get('core.manage')) {
            $toolbar->standardButton('refresh', 'COM_DHARMASHOP_REBUILD', 'categories.rebuild')->icon('icon-refresh')->listCheck(false);
        }

        if ($canDo->get('core.admin') || $canDo->get('core.options')) {
            $toolbar->preferences('com_dharmashop');
        }
    }
}
