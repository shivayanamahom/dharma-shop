<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Controller;

use Dharma\Component\DharmaShop\Administrator\Support\CategoryTypes;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;

\defined('_JEXEC') or die;

/**
 * Операции над списком категорий выбранного типа: публикация, архив, корзина, удаление, порядок, «Перестроить».
 */
class CategoriesController extends AdminController
{
    protected $option = 'com_dharmashop';

    protected $text_prefix = 'COM_DHARMASHOP';

    protected $view_list = 'categories';

    public function getModel($name = 'Category', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    protected function getRedirectToListAppend(): string
    {
        return '&extension=' . CategoryTypes::normalize($this->input->getString('extension', CategoryTypes::CATEGORY));
    }

    /** Кнопка «Перестроить»: пересчитывает дерево и пути категорий. */
    public function rebuild(): void
    {
        $this->checkToken();

        if (!$this->app->getIdentity()->authorise('core.manage', 'com_dharmashop')) {
            $this->setRedirect($this->getRedirectUrlToList(), Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 'error');

            return;
        }

        $model = $this->getModel();

        if ($model->rebuild()) {
            $this->setRedirect($this->getRedirectUrlToList(), Text::_('COM_DHARMASHOP_REBUILD_SUCCESS'));
        } else {
            $this->setRedirect($this->getRedirectUrlToList(), Text::sprintf('COM_DHARMASHOP_REBUILD_FAILED', $model->getError()), 'error');
        }
    }
}
