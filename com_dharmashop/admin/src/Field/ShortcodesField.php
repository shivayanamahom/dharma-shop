<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Field;

use Dharma\Component\DharmaShop\Administrator\Seo\PlaceholderCatalog;
use Dharma\Component\DharmaShop\Administrator\Seo\PlaceholderPanel;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Uri\Uri;

\defined('_JEXEC') or die;

/** Справочник всех областей для шаблонов SEO в настройках; сохраняемого значения нет. */
class ShortcodesField extends FormField
{
    protected $type = 'Shortcodes';

    protected function getInput(): string
    {
        $values = [
            'global.sitename' => (string) Factory::getApplication()->get('sitename'),
            'global.currency' => (string) ComponentHelper::getParams('com_dharmashop')->get('currency_symbol', '₽'),
            'global.url' => rtrim(Uri::root(), '/') . '/',
        ];
        $groups = [];
        $seen = [];

        foreach (['category', 'brand', 'badge', 'product'] as $area) {
            foreach (PlaceholderCatalog::describe($area, $values) as $group) {
                // Общие коды есть в каждой области; показываем их один раз.
                $key = $group['items'][0]['code'];

                if (!isset($seen[$key])) {
                    $groups[] = $group;
                    $seen[$key] = true;
                }
            }
        }

        return PlaceholderPanel::button() . PlaceholderPanel::dialog($groups, 'COM_DHARMASHOP_PH_SETTINGS_NOTE');
    }
}
