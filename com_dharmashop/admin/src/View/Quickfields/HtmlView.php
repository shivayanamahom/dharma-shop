<?php

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\View\Quickfields;

use Dharma\Component\DharmaShop\Administrator\Fields\TypeRegistry;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;

\defined('_JEXEC') or die;

final class HtmlView extends BaseHtmlView
{
    protected array $items = [];
    protected array $typeLabels = [];
    protected string $target = 'product';
    protected array $fieldIds = [];
    protected bool $canCreate = false;
    protected ?Pagination $pagination = null;
    public ?Form $filterForm = null;
    public array $activeFilters = [];

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        $input = $app->getInput();
        $this->target = $input->getCmd('target', 'product');
        $this->target = in_array($this->target, ['product', 'category'], true) ? $this->target : 'product';
        $this->fieldIds = array_values(array_unique(array_filter(array_map('intval', explode(',', $input->getString('field_ids', ''))))));
        $user = $app->getIdentity();
        if (!$user->authorise('core.edit', 'com_dharmashop')) {
            throw new \RuntimeException(Text::_('JLIB_APPLICATION_ERROR_CREATE_RECORD_NOT_PERMITTED'), 403);
        }
        $this->canCreate = $user->authorise('core.create', 'com_dharmashop') && $user->authorise('dharmashop.fields.manage', 'com_dharmashop');

        foreach (TypeRegistry::current()->all() as $id => $type) {
            $this->typeLabels[$id] = Text::_($type->label());
        }
        $model = $this->getModel();
        $this->items = $model->getItems();
        $this->pagination = $model->getPagination();
        $this->filterForm = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();
        if (!$this->activeFilters) { $this->activeFilters = ['open' => '1']; }
        $this->getDocument()->getWebAssetManager()->useScript('com_dharmashop.admin-quick-create');
        parent::display($tpl);
    }
}
