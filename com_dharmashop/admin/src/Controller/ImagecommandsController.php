<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Controller;
use Dharma\Component\DharmaShop\Administrator\Image\ImageMaintenance;
use Dharma\Component\DharmaShop\Administrator\Support\BatchProgressResponse;
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
        $ajax = $this->input->post->getBool('batch_ajax');
        if ($this->input->getMethod() !== 'POST' || !$this->app->getIdentity()->authorise('core.manage',$this->option)) {
            if ($ajax) BatchProgressResponse::send(['success'=>false,'error'=>Text::_('JERROR_ALERTNOAUTHOR')],403);
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'),403);
        }
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
            if ($ajax) BatchProgressResponse::send(['success'=>false,'error'=>Text::_('COM_DHARMASHOP_PROGRESS_FAILED')],400);
        }

        if ($ajax) {
            $job = (array) $this->app->getUserState($key, []);
            $phase = (string) ($job['phase'] ?? 'done');
            $done = in_array($phase, ['review','done'], true);
            $unit = in_array($phase, ['preview','review','clear'], true) || ($phase === 'done' && $operation === 'clear')
                ? Text::_('COM_DHARMASHOP_IMAGE_COMMAND_COUNT_FILES_UNIT')
                : Text::_('COM_DHARMASHOP_PROGRESS_RECORDS');
            if ($phase === 'clear' || ($phase === 'done' && $operation === 'clear')) {
                $processed = (int) ($job['deleted'] ?? 0) + (int) ($job['failed'] ?? 0);
                $total = (int) ($job['files'] ?? 0);
            } elseif ($phase === 'preview' || $phase === 'review') {
                $processed = (int) ($job['files'] ?? 0);
                $total = $phase === 'review' ? $processed : 0;
            } else {
                $processed = (int) ($job['records'] ?? 0);
                $total = (int) ($job['record_total'] ?? 0);
            }
            BatchProgressResponse::send([
                'success'=>true,
                'phase'=>$phase,
                'done'=>$done,
                'title'=>Text::_($operation === 'prepare' ? 'COM_DHARMASHOP_IMAGE_COMMAND_PREPARE' : 'COM_DHARMASHOP_IMAGE_COMMAND_CLEAR'),
                'processed'=>$processed,
                'total'=>$total,
                'unit'=>$unit,
                'files'=>(int) ($job['files'] ?? 0),
                'deleted'=>(int) ($job['deleted'] ?? 0),
                'bytes'=>(int) ($job['bytes'] ?? 0),
                'protected'=>count($job['protected'] ?? []),
            ]);
        }
        $this->setRedirect(Route::_('index.php?option=com_dharmashop&view=imagecommands&operation='.$operation,false));
    }
}
