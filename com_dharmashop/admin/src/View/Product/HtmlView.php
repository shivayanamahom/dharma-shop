<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\View\Product;

use Dharma\Component\DharmaShop\Administrator\Seo\PlaceholderCatalog;
use Dharma\Component\DharmaShop\Administrator\Support\FieldGroupTabs;
use Dharma\Component\DharmaShop\Administrator\Support\QuickCreateToolbar;
use Dharma\Component\DharmaShop\Site\Placeholder\ProductValues;
use Dharma\Component\DharmaShop\Site\Placeholder\GlobalValues;
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;

\defined('_JEXEC') or die;

/**
 * Форма товара.
 */
class HtmlView extends BaseHtmlView
{
    protected $option = 'com_dharmashop';

    protected ?Form $form = null;

    protected ?object $item = null;

    /** После сохранения открыть диалог быстрого создания поля. */
    public ?string $quickCreate = null;
    public array $quickFieldIds = [];

    /** Опубликованные группы соответствующего назначения для вкладок дополнительных полей. */
    protected array $fieldGroups = [];

    /**
     * Список полей для штатных блоков `joomla.edit.*` (они читают свойство `fields` вида). Задаётся в шаблоне только на время вывода
     * бокового блока: иначе его подхватит и блок «Публикация».
     */
    public ?array $fields = null;

    /** Справочник шорткодов с текущими значениями товара. @var array<int, array{title: string, items: array<int, array{code: string, name: string, value: string}>}> */
    protected array $placeholders = [];

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        $this->quickCreate = $app->getUserState('com_dharmashop.quick_create');
        $app->setUserState('com_dharmashop.quick_create', null);
        $this->quickFieldIds = (array) $app->getUserState('com_dharmashop.quick_field_ids', []);
        $app->setUserState('com_dharmashop.quick_field_ids', []);

        /** @var \Dharma\Component\DharmaShop\Administrator\Model\ProductModel $model */
        $model = $this->getModel();

        $item = $model->getItem();
        $this->form = $model->getForm();

        if (!$item) {
            // Запись не найдена (удалена, пока была открыта форма): не падаем, возвращаем к списку.
            $app = Factory::getApplication();
            $app->enqueueMessage(Text::_('COM_DHARMASHOP_ERROR_PRODUCT_NOT_FOUND'), 'error');
            $app->redirect(Route::_('index.php?option=com_dharmashop&view=products', false));

            return;
        }

        $this->item = $item;

        if ($errors = $model->getErrors()) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        $this->fieldGroups = FieldGroupTabs::load(Factory::getContainer()->get(DatabaseInterface::class), 'product');
        $this->buildPlaceholders();
        $this->addToolbar();

        parent::display($tpl);
    }

    /** Справочник шорткодов: названия, коды и значения этого товара (у нового — прочерки). */
    private function buildPlaceholders(): void
    {
        $shop = Factory::getApplication()->bootComponent('com_dharmashop')->getServices();
        $money = $shop->money();
        $db = Factory::getContainer()->get('DatabaseDriver');
        $item = $this->item;
        $id = (int) $item->id;
        $none = '—';
        $titleOf = static function (int $catid) use ($db): string {
            return $catid > 1 ? (string) $db->setQuery('SELECT title FROM #__categories WHERE id = ' . $catid)->loadResult() : '';
        };
        $has = $id > 0;
        $preview = clone $item;
        $preview->category_title = $titleOf((int) ($item->catid ?? 0));
        $preview->brand_title = $titleOf((int) ($item->brand_id ?? 0));
        $effective = $has && $item->price_min !== null ? (float) $item->price_min : null;
        $regular = $has && $item->price !== null ? (float) $item->price : null;
        $preview->base_price = $effective !== null && $regular !== null && $regular > $effective ? $regular : null;
        $preview->discount_percent = $preview->base_price !== null && $regular > 0 ? (1 - $effective / $regular) * 100 : 0;
        $preview->unit = (string) $shop->orderRules()->forProduct($item)['unit'];
        $values = $has ? ProductValues::build($preview, $money) : [];
        foreach ($values as &$value) {
            if ($value === '') {
                $value = $none;
            }
        }
        unset($value);
        if ($has) {
            $values['product.stock'] = Text::_((int) $item->in_stock === 1 ? 'COM_DHARMASHOP_IN_STOCK' : 'COM_DHARMASHOP_OUT_OF_STOCK');
        }
        $values += GlobalValues::build(ComponentHelper::getParams('com_dharmashop'));

        $this->placeholders = PlaceholderCatalog::describe('product', $values);
    }

    protected function addToolbar(): void
    {
        Factory::getApplication()->getInput()->set('hidemainmenu', true);

        $user = $this->getCurrentUser();
        $isNew = empty($this->item->id);
        $toolbar = Toolbar::getInstance();
        $canDo = ContentHelper::getActions('com_dharmashop', 'product', (int) $this->item->id);

        ToolbarHelper::title(Text::_($isNew ? 'COM_DHARMASHOP_PRODUCT_NEW' : 'COM_DHARMASHOP_PRODUCT_EDIT'), 'pencil-alt');

        $canSave = $isNew ? $canDo->get('core.create') : $canDo->get('core.edit') || ($canDo->get('core.edit.own') && (int) $this->item->created_by === (int) $user->id);

        if ($canSave) {
            $toolbar->apply('product.apply');
            $saveGroup = $toolbar->dropdownButton('save-group');
            $saveGroup->configure(
                function (Toolbar $childBar) use ($canDo, $isNew) {
                    $childBar->save('product.save');

                    if ($canDo->get('core.create')) {
                        $childBar->save2new('product.save2new');

                        if (!$isNew) {
                            $childBar->save2copy('product.save2copy');
                        }
                    }
                }
            );
        }

        QuickCreateToolbar::add(
            $toolbar,
            'product',
            'product-form',
            $canSave && $user->authorise('core.create', 'com_dharmashop'),
            !empty($this->item->id) && (int) $this->item->state === 1
                ? Route::link('site', 'index.php?option=com_dharmashop&view=product&id=' . (int) $this->item->id . '&catid=' . (int) $this->item->catid, false, Route::TLS_IGNORE, true)
                : ''
        );

        $toolbar->cancel('product.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');
    }
}
