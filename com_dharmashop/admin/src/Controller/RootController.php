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
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

\defined('_JEXEC') or die;

/**
 * Правка страницы корня раздела (корневая категория, корневой бренд): задачи edit, apply, save, cancel.
 */
class RootController extends BaseController
{
    protected $option = 'com_dharmashop';

    private function extension(): string
    {
        $extension = CategoryTypes::normalize($this->input->getString('extension', CategoryTypes::CATEGORY));

        return $extension === CategoryTypes::BRAND ? CategoryTypes::BRAND : CategoryTypes::CATEGORY;
    }

    private function listUrl(): string
    {
        return Route::_('index.php?option=com_dharmashop&view=categories&extension=' . $this->extension(), false);
    }

    private function editUrl(): string
    {
        return Route::_('index.php?option=com_dharmashop&task=root.edit&extension=' . $this->extension(), false);
    }

    private function allowed(): bool
    {
        return $this->app->getIdentity()->authorise('core.edit', 'com_dharmashop');
    }

    public function edit(): void
    {
        if (!$this->allowed()) {
            $this->setRedirect($this->listUrl(), Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 'error');

            return;
        }

        $this->input->set('view', 'root');
        $this->input->set('layout', 'edit');
        $this->display();
    }

    public function apply(): void
    {
        $this->store(false);
    }

    public function save(): void
    {
        $this->store(true);
    }

    public function cancel(): void
    {
        $this->checkToken();
        $this->setRedirect($this->listUrl());
    }

    private function store(bool $close): void
    {
        $this->checkToken();

        if (!$this->allowed()) {
            $this->setRedirect($this->listUrl(), Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 'error');

            return;
        }

        $model = $this->getModel('Root');
        $data = $this->input->post->get('jform', [], 'array');
        $key = 'com_dharmashop.edit.root.' . $model->getKind() . '.data';

        if (!$model->save($data)) {
            $this->app->setUserState($key, $data);
            $this->setRedirect($this->editUrl(), Text::sprintf('JLIB_APPLICATION_ERROR_SAVE_FAILED', $model->getError()), 'error');

            return;
        }

        $this->app->setUserState($key, null);
        $this->setRedirect($close ? $this->listUrl() : $this->editUrl(), Text::_('COM_DHARMASHOP_ROOT_SAVED'));
    }
}
