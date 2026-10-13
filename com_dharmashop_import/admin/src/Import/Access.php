<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShopImport\Administrator\Import;
use Joomla\CMS\Factory;
\defined('_JEXEC') or die;

final class Access
{
    public static function require(string $action): void
    {
        $user=Factory::getApplication()->getIdentity();
        if(!$user||!$user->authorise('core.manage','com_dharmashop_import')||!$user->authorise($action,'com_dharmashop_import')){throw new \RuntimeException('JERROR_ALERTNOAUTHOR',403);}
    }
    public static function job(array $job): void
    {
        self::require('core.manage');$u=Factory::getApplication()->getIdentity();
        if((int)$job['created_by']!==(int)$u->id&&!$u->authorise('dharmashop.import.report','com_dharmashop_import')){throw new \RuntimeException('JERROR_ALERTNOAUTHOR',403);}
    }
}
