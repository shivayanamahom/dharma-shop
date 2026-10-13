<?php

/**
 * Dharma Shop — группы самостоятельных товаров.
 * @copyright (C) 2026 Dharma.
 * @license GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Controller;

use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Dharma\Component\DharmaShop\Administrator\Support\BatchProgressResponse;

\defined('_JEXEC') or die;

/** Публикация, корзина, check-in и порядок групп через штатный AdminController. */
class ProductgroupsController extends AdminController
{
    use RequiresActionTrait;

    protected $option = 'com_dharmashop';

    protected function requiredAction(): string
    {
        return 'dharmashop.groups.manage';
    }
    protected $text_prefix = 'COM_DHARMASHOP';

    public function getModel($name = 'Productgroup', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    public function batch($model = null)
    {
        $this->checkToken();
        $this->context = 'productgroup';
        $model = $this->getModel('Productgroup', 'Administrator', []);
        $ajax = $this->input->getBool('batch_ajax');
        $this->setRedirect(Route::_('index.php?option=com_dharmashop&view=productgroups', false));

        $commands = $this->input->post->get('batch', [], 'array');
        $ids = array_filter((array) $this->input->post->get('cid', [], 'int'));
        $contexts = [];
        foreach ($ids as $id) {
            $contexts[$id] = $this->option . '.' . $this->context . '.' . $id;
        }

        if ($ajax && (!$this->app->getIdentity()->authorise('core.create', 'com_dharmashop') || !$this->app->getIdentity()->authorise('core.edit', 'com_dharmashop'))) {
            BatchProgressResponse::send(['success' => false, 'error' => Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN')], 403);
        }

        if ($model->batch($commands, $ids, $contexts)) {
            if ($ajax) {
                BatchProgressResponse::send(['success' => true, 'processed' => count($ids)]);
            }
            $this->setMessage(Text::_('JLIB_APPLICATION_SUCCESS_BATCH'));
            return true;
        }

        $this->setMessage(Text::sprintf('JLIB_APPLICATION_ERROR_BATCH_FAILED', $model->getError()), 'warning');
        return false;
    }
}
