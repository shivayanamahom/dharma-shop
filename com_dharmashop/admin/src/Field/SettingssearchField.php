<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Field;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

/** Поиск по настройкам com_config: интерфейс без сохраняемого значения, C72. */
class SettingssearchField extends FormField
{
    protected $type = 'Settingssearch';

    public function renderField($options = [])
    {
        return $this->getInput();
    }

    protected function getInput()
    {
        $app = Factory::getApplication();

        if (!$app->isClient('administrator') || $app->getInput()->getCmd('option') !== 'com_config'
            || $app->getInput()->getCmd('component') !== 'com_dharmashop') {
            return '';
        }

        $document = $app->getDocument();
        $wa = $document->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_dharmashop');
        $wa->useScript('com_dharmashop.settings-search');
        $document->addScriptOptions('com_dharmashop.settingssearch', [
            'empty' => Text::_('COM_DHARMASHOP_SETTINGS_SEARCH_EMPTY'),
            'count' => Text::_('COM_DHARMASHOP_SETTINGS_SEARCH_COUNT'),
            'conditional' => Text::_('COM_DHARMASHOP_SETTINGS_SEARCH_CONDITIONAL'),
            'condition' => Text::_('COM_DHARMASHOP_SETTINGS_SEARCH_CONDITION'),
            'row' => Text::_('COM_DHARMASHOP_SETTINGS_SEARCH_ROW'),
            'unavailable' => Text::_('COM_DHARMASHOP_SETTINGS_SEARCH_UNAVAILABLE'),
        ]);
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $title = $escape(Text::_('COM_DHARMASHOP_SETTINGS_SEARCH_TITLE'));
        $hint = $escape(Text::_('COM_DHARMASHOP_SETTINGS_SEARCH_HINT'));
        $clear = $escape(Text::_('COM_DHARMASHOP_SETTINGS_SEARCH_CLEAR'));
        $help = $escape(Text::_('COM_DHARMASHOP_SETTINGS_SEARCH_HELP'));
        $more = $escape(Text::_('COM_DHARMASHOP_SETTINGS_SEARCH_MORE'));

        // У input намеренно нет name: запрос поиска не попадает в params при сохранении com_config.
        return '<div class="card bg-body-tertiary mb-3" data-dharmashop-settings-search hidden>'
            . '<div class="card-body p-3">'
            . '<label class="form-label fw-semibold" for="dharma-settings-search">' . $title . '</label>'
            . '<div class="input-group">'
            . '<span class="input-group-text"><span class="icon-search" aria-hidden="true"></span></span>'
            . '<input id="dharma-settings-search" type="search" class="form-control" placeholder="' . $hint . '" autocomplete="off"'
            . ' aria-describedby="dharma-settings-search-help" aria-controls="dharma-settings-search-results" aria-expanded="false">'
            . '<button type="button" class="btn btn-outline-secondary" data-settings-clear disabled>' . $clear . '</button></div>'
            . '<div id="dharma-settings-search-help" class="form-text">' . $help . '</div>'
            . '<div class="small mt-2" data-settings-status role="status" aria-live="polite" aria-atomic="true"></div>'
            . '<div id="dharma-settings-search-results" class="list-group mt-2" data-settings-results hidden></div>'
            . '<button type="button" class="btn btn-sm btn-outline-secondary mt-2" data-settings-more hidden>' . $more . '</button>'
            . '</div></div>';
    }
}
