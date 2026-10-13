<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShopImport\Administrator\Model;
\defined('_JEXEC') or die;
class JobsModel extends \Joomla\CMS\MVC\Model\ListModel
{
    protected $option='com_dharmashop_import';
    protected function getListQuery(){return $this->getDatabase()->createQuery()->select('j.id,j.profile_id,j.state,j.created,j.finished,j.total,j.processed,j.errors,j.created_count,j.updated_count,j.unchanged_count,p.title')->from('#__dharmashop_import_jobs j')->join('LEFT','#__dharmashop_import_profiles p ON p.id=j.profile_id')->order('j.id DESC');}
}
