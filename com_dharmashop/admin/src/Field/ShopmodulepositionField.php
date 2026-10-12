<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Field;
use Dharma\Component\DharmaShop\Site\Support\CatalogModules;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Component\Modules\Administrator\Field\ModulesPositioneditField;
use Joomla\Component\Modules\Administrator\Helper\ModulesHelper;
\defined('_JEXEC') or die;
/** Native Joomla grouped position picker, extended with a component group. */
final class ShopmodulepositionField extends ModulesPositioneditField
{
    protected $type = 'Shopmoduleposition';
    protected function getInput()
    {
        $data = $this->getLayoutData();
        $clientId = $this->client === 'administrator' ? 1 : 0;
        $positions = HTMLHelper::_('modules.positions', $clientId, 1, $this->value);
        if ($clientId === 0) {
            Factory::getApplication()->getLanguage()->load('com_dharmashop', JPATH_ADMINISTRATOR);
            $options = [];
            foreach (CatalogModules::POSITIONS as $key => $position) {
                $options[] = ModulesHelper::createOption($position, Text::_('COM_DHARMASHOP_MODULE_POSITION_' . strtoupper($key)) . ' [' . $position . ']');
            }
            $positions['dharmashop'] = ModulesHelper::createOptionGroup(Text::_('COM_DHARMASHOP_MODULE_POSITIONS'), $options);
            // A selected component position may also occur in the native custom group.
            foreach ($positions as $key => &$group) {
                if ($key === 'dharmashop') { continue; }
                $group['items'] = array_values(array_filter($group['items'] ?? [], static fn($item): bool => !in_array($item->value, CatalogModules::POSITIONS, true)));
            }
            unset($group);
        }
        $data['client'] = $clientId;
        $data['positions'] = $positions;
        $renderer = $this->getRenderer($this->layout);
        $renderer->setComponent('com_modules');
        $renderer->setClient(1);
        return $renderer->render($data);
    }
}
