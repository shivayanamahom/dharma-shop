<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Controller;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\MVC\Controller\AdminController;

\defined('_JEXEC') or die;

/** Публикация, порядок и удаление способов доставки через штатный AdminController. */
class PaymentsController extends AdminController
{
    protected $option = 'com_dharmashop';
    protected $text_prefix = 'COM_DHARMASHOP';

    public function getModel($name = 'Payment', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    /** Звезда в списке: назначить способ по умолчанию. */
    public function setDefault(): void
    {
        $this->changeDefault(true);
    }

    /** Звезда в списке: снять отметку «по умолчанию». */
    public function unsetDefault(): void
    {
        $this->changeDefault(false);
    }

    private function changeDefault(bool $default): void
    {
        $this->checkToken();
        $url = Route::_('index.php?option=com_dharmashop&view=payments', false);

        if (!$this->app->getIdentity()->authorise('core.edit.state', $this->option)) {
            $this->setRedirect($url, Text::_('JLIB_APPLICATION_ERROR_EDITSTATE_NOT_PERMITTED'), 'error');

            return;
        }

        $cid = (array) $this->input->get('cid', [], 'array');
        $model = $this->getModel();

        if ($model->setDefault((int) ($cid[0] ?? 0), $default)) {
            $this->setRedirect($url, Text::_($default ? 'COM_DHARMASHOP_PAYMENT_DEFAULT_SET' : 'COM_DHARMASHOP_PAYMENT_DEFAULT_UNSET'));
        } else {
            $this->setRedirect($url, $model->getError(), 'warning');
        }
    }

    public function execute($task)
    {
        if (!$this->app->getIdentity()->authorise('dharmashop.payment.manage', $this->option)) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        return parent::execute($task);
    }
}
