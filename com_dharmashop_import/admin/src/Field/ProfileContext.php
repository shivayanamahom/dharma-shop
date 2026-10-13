<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShopImport\Administrator\Field;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Dharma\Component\DharmaShopImport\Administrator\Import\Workflow;
\defined('_JEXEC') or die;
final class ProfileContext
{
    private static ?array $profile=null;
    public static function get():array{
        if(self::$profile===null){$id=Factory::getApplication()->getInput()->getInt('id');self::$profile=$id?(new Workflow(Factory::getContainer()->get(DatabaseInterface::class)))->profile($id):['entity'=>'categories','params'=>[]];}
        return self::$profile;
    }
}
