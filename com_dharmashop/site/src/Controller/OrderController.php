<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Controller;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/** Действия покупателя над своим заказом. Только POST с токеном формы. */
class OrderController extends BaseController
{
    protected $option = 'com_dharmashop';

    /**
     * Привязка гостевого заказа к аккаунту вошедшего покупателя. Доказательство владения — секретный ключ заказа из ссылки в письме
     * (128 бит, уходит только на e-mail заказа); без ключа или с чужим ключом привязки нет. Заказ с владельцем не перепривязывается.
     */
    public function claim(): void
    {
        $id = $this->input->post->getInt('id');
        $key = (string) $this->input->post->get('key', '', 'cmd');
        $identity = $this->app->getIdentity();
        $back = Route::_('index.php?option=com_dharmashop&view=order&id=' . $id . '&key=' . $key, false);

        if ($this->input->getMethod() !== 'POST' || !Session::checkToken('post') || !$identity || $identity->guest || $id < 1 || preg_match('/^[a-f0-9]{32}$/', $key) !== 1) {
            $this->setRedirect($back, Text::_('COM_DHARMASHOP_ORDER_CLAIM_FAILED'), 'warning');

            return;
        }

        $db = \Joomla\CMS\Factory::getContainer()->get(DatabaseInterface::class);
        $user = (int) $identity->id;
        $query = $db->createQuery()->update($db->quoteName('#__dharmashop_orders'))->set($db->quoteName('user_id') . ' = :user')
            ->where($db->quoteName('id') . ' = :id')->where($db->quoteName('user_id') . ' IS NULL')->where($db->quoteName('secret') . ' = :secret')
            ->bind(':user', $user, ParameterType::INTEGER)->bind(':id', $id, ParameterType::INTEGER)->bind(':secret', $key);
        $db->setQuery($query)->execute();

        if ($db->getAffectedRows() < 1) {
            $this->setRedirect($back, Text::_('COM_DHARMASHOP_ORDER_CLAIM_FAILED'), 'warning');

            return;
        }

        $this->setRedirect(Route::_('index.php?option=com_dharmashop&view=order&id=' . $id, false), Text::_('COM_DHARMASHOP_ORDER_CLAIMED'));
    }
}
