<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Controller;
use Dharma\Component\DharmaShop\Administrator\Image\ImageMaintenance;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
\defined('_JEXEC') or die;
final class ImagecommandsController extends BaseController
{
    protected $option = 'com_dharmashop';
    public function start(): void { $this->executeBatch('start'); }
    public function proceed(): void { $this->executeBatch('proceed'); }
    public function confirm(): void { $this->executeBatch('confirm'); }
    public function reset(): void { $this->executeBatch('reset'); }

    private function executeBatch(string $action): void
    {
        $this->checkToken('post');
        if ($this->input->getMethod() !== 'POST' || !$this->app->getIdentity()->authorise('core.manage',$this->option)) throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'),403);
        $operation = $this->input->post->getCmd('operation');
        if (!in_array($operation,['prepare','clear'],true)) throw new \RuntimeException(Text::_('JERROR_LAYOUT_PAGE_NOT_FOUND'),404);
        $key = 'com_dharmashop.images.'.$operation;
        try {
            if ($action === 'reset') $this->app->setUserState($key,null);
            else {
                $service = new ImageMaintenance(Factory::getContainer()->get(DatabaseInterface::class),ComponentHelper::getParams($this->option));
                $state = (array)$this->app->getUserState($key,[]);
                if ($action === 'start') {
                    if ($state) throw new \RuntimeException('COM_DHARMASHOP_IMAGE_COMMAND_RESTART');
                    $state = $service->start($operation,$operation==='prepare' ? $this->input->post->getString('context') : '');
                } elseif (!$state) throw new \RuntimeException('COM_DHARMASHOP_IMAGE_COMMAND_RESTART');
                if ($action === 'confirm') $state = $service->confirm($state);
                $this->app->setUserState($key,$service->run($state));
            }
        } catch (\Throwable $error) {
            Log::add('Image maintenance: '.get_class($error).': '.$error->getMessage(),Log::WARNING,'com_dharmashop');
            $message = str_starts_with($error->getMessage(),'COM_DHARMASHOP_IMAGE_COMMAND_') ? $error->getMessage() : 'COM_DHARMASHOP_IMAGE_COMMAND_ERROR';
            $this->app->enqueueMessage(Text::_($message),'error');
        }
        $this->setRedirect(Route::_('index.php?option=com_dharmashop&view=imagecommands&operation='.$operation,false));
    }
}
