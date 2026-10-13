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
    /**
     * Запуск без человека (CLI, планировщик): выполняется от имени существующего пользователя, чтобы действовали обычные права на записи и отчёты.
     * $who — ID или логин; пусто — первый разблокированный суперпользователь.
     */
    public static function actAs(int|string|null $who = null): \Joomla\CMS\User\User
    {
        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $query = $db->createQuery()->select($db->quoteName('id'))->from($db->quoteName('#__users'))->where($db->quoteName('block') . ' = 0');
        if ($who !== null && $who !== '' && $who !== 0 && $who !== '0') {
            if (is_int($who) || ctype_digit((string) $who)) {
                $id = (int) $who;
                $query->where($db->quoteName('id') . ' = :id')->bind(':id', $id, \Joomla\Database\ParameterType::INTEGER);
            } else {
                $name = (string) $who;
                $query->where($db->quoteName('username') . ' = :name')->bind(':name', $name);
            }
            $id = (int) $db->setQuery($query)->loadResult();
        } else {
            $id = 0;
            foreach ($db->setQuery($query->order($db->quoteName('id')))->loadColumn() as $candidate) {
                $user = \Joomla\CMS\User\User::getInstance((int) $candidate);
                if ($user->authorise('core.admin')) { $id = (int) $candidate; break; }
            }
        }
        if ($id < 1) { throw new \RuntimeException('COM_DHARMASHOP_IMPORT_USER_NOT_FOUND'); }
        $user = \Joomla\CMS\User\User::getInstance($id);
        $app = Factory::getApplication();
        if (!method_exists($app, 'loadIdentity')) { throw new \RuntimeException('JERROR_ALERTNOAUTHOR', 403); }
        $app->loadIdentity($user);

        return $user;
    }

    public static function job(array $job): void
    {
        self::require('core.manage');$u=Factory::getApplication()->getIdentity();
        if((int)$job['created_by']!==(int)$u->id&&!$u->authorise('dharmashop.import.report','com_dharmashop_import')){throw new \RuntimeException('JERROR_ALERTNOAUTHOR',403);}
    }
}
