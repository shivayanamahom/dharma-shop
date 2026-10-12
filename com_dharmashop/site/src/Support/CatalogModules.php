<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Site\Support;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
\defined('_JEXEC') or die;
/** Native module loading respects menu assignment, publication, language and ACL. */
final class CatalogModules
{
    public const POSITIONS = [
        'top' => 'dharmashop-products-top',
        'left' => 'dharmashop-products-left',
        'right' => 'dharmashop-products-right',
    ];
    public static function render(): array
    {
        $slots = array_fill_keys(array_keys(self::POSITIONS), '');
        $app = Factory::getApplication();
        if (!$app->isClient('site') || !$app->getDocument() instanceof HtmlDocument) { return $slots; }
        foreach (self::POSITIONS as $key => $position) {
            foreach (ModuleHelper::getModules($position) as $module) {
                $html = ModuleHelper::renderModule($module, ['style' => 'html5']);
                if (trim($html) !== '') { $slots[$key] .= $html; }
            }
        }
        return $slots;
    }
}
