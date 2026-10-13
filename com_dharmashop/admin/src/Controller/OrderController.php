<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Controller;
use Dharma\Component\DharmaShop\Administrator\Support\Sql;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
\defined('_JEXEC') or die;
class OrderController extends BaseController
{
    protected $option = 'com_dharmashop';
    private function allowed(): void
    {
        if ($this->input->getMethod() !== 'POST' || !Session::checkToken('post')) throw new \RuntimeException(Text::_('JINVALID_TOKEN'), 403);
        if (!$this->app->getIdentity()->authorise('dharmashop.orders.manage', $this->option)) throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
    }
    public function changeStatus(): void
    {
        $this->allowed();
        if (!$this->app->getIdentity()->authorise('dharmashop.orders.status', $this->option)) throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        $id = $this->input->post->getInt('id');
        $url = Route::_('index.php?option=com_dharmashop&view=order&id=' . $id, false);
        try {
            $this->app->bootComponent($this->option)->getServices()->orderStatuses()->change($id,
                $this->input->post->getInt('status_id'), $this->input->post->getInt('previous_status_id'), (int) $this->app->getIdentity()->id);
            $this->setRedirect($url, Text::_('COM_DHARMASHOP_ORDER_STATUS_SAVED'));
        } catch (\DomainException $error) {
            $this->setRedirect($url, $error->getMessage(), 'warning');
        } catch (\Throwable $error) {
            Log::add('Order status failed: ' . get_class($error), Log::ERROR, 'com_dharmashop');
            $this->setRedirect($url, Text::_('COM_DHARMASHOP_ORDER_STATUS_FAILED'), 'error');
        }
    }
    public function retryNotifications(): void
    {
        $this->allowed();
        $id = $this->input->post->getInt('id');
        $historyId = $this->input->post->getInt('history_id');
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $exists = $db->setQuery(Sql::query($db, 'SELECT id FROM #__dharmashop_order_history WHERE id=:h AND order_id=:o', [':h'=>[$historyId,ParameterType::INTEGER], ':o'=>[$id,ParameterType::INTEGER]]))->loadResult();
        if (!$exists) throw new \RuntimeException(Text::_('JERROR_LAYOUT_PAGE_NOT_FOUND'), 404);
        $this->app->bootComponent($this->option)->getServices()->notifications()->notify($historyId, true);
        $this->setRedirect(Route::_('index.php?option=com_dharmashop&view=order&id=' . $id, false), Text::_('COM_DHARMASHOP_NOTIFY_RETRY_DONE'));
    }
}
