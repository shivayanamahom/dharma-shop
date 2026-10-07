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

    /**
     * Список полей для штатных блоков `joomla.edit.*` (они читают свойство `fields` вида). Задаётся в шаблоне только на время вывода
     * бокового блока: иначе его подхватит и блок «Публикация».
     */
    public ?array $fields = null;

    /** Справочник шорткодов с текущими значениями товара. @var array<int, array{title: string, items: array<int, array{code: string, name: string, value: string}>}> */
    protected array $placeholders = [];

    public function display($tpl = null): void
    {
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
        $effective = $has && $item->price_min !== null ? (float) $item->price_min : null;
        $regular = $has && $item->price !== null ? (float) $item->price : null;
        $values = [
            'product.title' => $has ? (string) $item->title : $none,
            'product.sku' => $has && (string) $item->sku !== '' ? (string) $item->sku : $none,
            'product.category' => ($t = $titleOf((int) ($item->catid ?? 0))) !== '' ? $t : $none,
            'product.brand' => ($t = $titleOf((int) ($item->brand_id ?? 0))) !== '' ? $t : $none,
            'product.price' => $effective !== null ? $money->format($effective) : $none,
            'product.price_old' => $effective !== null && $regular !== null && $regular > $effective ? $money->format($regular) : $none,
            'product.unit' => $has ? (string) $shop->orderRules()->forProduct($item)['unit'] ?: $none : $none,
            'product.stock' => $has ? Text::_((int) $item->in_stock === 1 ? 'COM_DHARMASHOP_IN_STOCK' : 'COM_DHARMASHOP_OUT_OF_STOCK') : $none,
            'global.sitename' => (string) Factory::getApplication()->get('sitename'),
            'global.currency' => (string) ComponentHelper::getParams('com_dharmashop')->get('currency_symbol', '₽'),
        ];

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
                function (Toolbar $childBar) use ($canDo) {
                    $childBar->save('product.save');

                    if ($canDo->get('core.create')) {
                        $childBar->save2new('product.save2new');
                    }
                }
            );
        }

        $toolbar->cancel('product.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');
    }
}
