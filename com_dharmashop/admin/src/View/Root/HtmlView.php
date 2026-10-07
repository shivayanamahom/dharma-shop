<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\View\Root;

use Dharma\Component\DharmaShop\Administrator\Seo\PlaceholderCatalog;
use Dharma\Component\DharmaShop\Administrator\Support\CategoryTypes;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;

\defined('_JEXEC') or die;

/** Форма страницы корня раздела: основное и SEO. */
class HtmlView extends BaseHtmlView
{
    protected $option = 'com_dharmashop';

    protected ?Form $form = null;

    protected string $kind = 'category';

    protected string $extension = CategoryTypes::CATEGORY;

    /** @var array<int, array{title: string, items: array<int, array{code: string, name: string, value: string}>}> */
    protected array $placeholders = [];

    public function display($tpl = null): void
    {
        $model = $this->getModel();
        $this->form = $model->getForm();
        $this->kind = $model->getKind();
        $this->extension = $this->kind === 'brand' ? CategoryTypes::BRAND : CategoryTypes::CATEGORY;

        $data = $this->form->getData();
        $title = trim((string) $data->get('title', ''));
        $none = '—';
        $values = [
            $this->kind . '.title' => $title !== '' ? $title : Text::_('COM_DHARMASHOP_ROOT_DEFAULT_TITLE_' . strtoupper($this->kind)),
            'global.sitename' => (string) Factory::getApplication()->get('sitename'),
            'global.currency' => (string) ComponentHelper::getParams('com_dharmashop')->get('currency_symbol', '₽'),
        ];

        foreach (PlaceholderCatalog::groups($this->kind, true) as $group) {
            $items = [];

            foreach ($group['items'] as $code) {
                $items[] = ['code' => '{' . $code . '}', 'name' => Text::_(PlaceholderCatalog::labelKey($code)), 'value' => $values[$code] ?? $none];
            }

            $this->placeholders[] = ['title' => Text::_($group['label']), 'items' => $items];
        }

        $this->addToolbar();

        parent::display($tpl);
    }

    protected function addToolbar(): void
    {
        Factory::getApplication()->getInput()->set('hidemainmenu', true);

        ToolbarHelper::title(Text::_('COM_DHARMASHOP_ROOT_EDIT_TITLE_' . strtoupper($this->kind)), 'pencil-alt');

        $toolbar = Toolbar::getInstance();
        $toolbar->apply('root.apply');
        $toolbar->save('root.save');
        $toolbar->cancel('root.cancel', 'JTOOLBAR_CLOSE');
    }
}
