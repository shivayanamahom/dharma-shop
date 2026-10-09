<?php

/**
 * Dharma Shop — группы самостоятельных товаров.
 * @copyright (C) 2026 Dharma.
 * @license GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Controller;

use Joomla\CMS\MVC\Controller\FormController;

\defined('_JEXEC') or die;

/** Группы товаров: нативные сохранение, отмена и блокировка формы Joomla. */
class ProductgroupController extends FormController
{
    protected $option = 'com_dharmashop';
    protected $text_prefix = 'COM_DHARMASHOP';
    protected $view_list = 'productgroups';
    public function add()
    {
        $result = parent::add();
        if ($result && !$this->input->getBool('from_products')) {
            $this->app->setUserState('com_dharmashop.group.selection', null);
        }
        return $result;
    }

    public function cancel($key = null)
    {
        $result = parent::cancel($key);
        if ($result) { $this->app->setUserState('com_dharmashop.group.selection', null); }
        return $result;
    }

    public function generate(): void
    {
        $this->checkToken();
        $id=$this->input->getInt('id');$user=$this->app->getIdentity();
        if(!$id || !$user->authorise('core.create','com_dharmashop') || !$user->authorise('core.edit','com_dharmashop')) {throw new \RuntimeException(\Joomla\CMS\Language\Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'),403);}
        $model=$this->getModel();$model->setState('productgroup.id',$id);
        $raw=(array)$this->input->post->get('jform',[],'array');$raw['id']=$id;
        $this->app->setUserState('com_dharmashop.edit.productgroup.data',$raw);
        try {
            $form=$model->getForm($raw,false);$data=$model->validate($form,$raw);
            if($data===false) {throw new \RuntimeException(implode('; ',array_map('strval',$model->getErrors())));}
            $result=$model->createExecutions($id,$data);
            $saved=$model->getItem($id);
            $raw['members']=$saved->members;$raw['axes']=$saved->axes;
            $raw['revision']=(int)($raw['revision'] ?? 0)+count($result['created']);
            $raw['executions']=$result['remaining'];$this->app->setUserState('com_dharmashop.edit.productgroup.data',$raw);
            $message=\Joomla\CMS\Language\Text::sprintf('COM_DHARMASHOP_GROUP_CREATED',count($result['created']));
            if($result['error']!=='') {$message.=' '. $result['error'];}
            $this->setMessage($message,$result['error']!==''?'warning':'message');
        } catch(\Throwable $e) {$this->setMessage(\Joomla\CMS\Language\Text::_($e->getMessage()),'error');}
        $this->setRedirect(\Joomla\CMS\Router\Route::_('index.php?option=com_dharmashop&view=productgroup&layout=edit&id='.$id,false));
    }
}
