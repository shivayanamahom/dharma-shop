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

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

\defined('_JEXEC') or die;

/**
 * Показывает страницы компонента; по умолчанию — список товаров.
 */
class DisplayController extends BaseController
{
    /** Задаём явно, чтобы не зависеть от вывода имени компонента из пространства имён. */
    protected $option = 'com_dharmashop';

    protected $default_view = 'products';

    public function display($cachable = false, $urlparams = [])
    {
        // Кнопки вкладки «Расширения» лежат внутри формы настроек, а в её теле есть пустое поле task, которое перекрывает task из адреса:
        // поэтому действие передаётся отдельным параметром и здесь передаётся LibrariesController (токен и права проверяет он).
        $libraries = $this->input->getCmd('libraries_action');

        if (in_array($libraries, ['update', 'rollback', 'check'], true)) {
            $controller = $this->factory->createController('Libraries', 'Administrator', [], $this->app, $this->input);
            $controller->execute($libraries);
            $controller->redirect();

            return $this;
        }

        $view = $this->input->getCmd('view');
        if (in_array($view, ['fieldgroup', 'field', 'status'], true) && $this->input->getCmd('layout') === 'edit') {
            $id = $this->input->getInt('id');
            if (!$this->checkEditId('com_dharmashop.edit.' . $view, $id)) {
                $this->setRedirect(Route::_('index.php?option=com_dharmashop&view=' . (['field'=>'fields','fieldgroup'=>'fieldgroups','status'=>'statuses'][$view]), false), Text::_('JLIB_APPLICATION_ERROR_UNHELD_ID'), 'error');
                return false;
            }
        }

        if ($view === 'customer' && $this->input->getCmd('layout') === 'edit'
            && $this->app->getIdentity()->authorise('core.edit', $this->option)
            && !$this->checkEditId('com_dharmashop.edit.customer', $this->input->getInt('id'))) {
            $this->setRedirect(Route::_('index.php?option=com_dharmashop&view=customers', false), Text::_('JLIB_APPLICATION_ERROR_UNHELD_ID'), 'error');
            return false;
        }

        return parent::display($cachable, $urlparams);
    }
}
