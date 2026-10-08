<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Field;

use Dharma\Component\DharmaShop\Administrator\Seo\PlaceholderCatalog;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

/** Справочник всех областей для шаблонов SEO в настройках; сохраняемого значения нет. */
class ShortcodesField extends FormField
{
    protected $type = 'Shortcodes';

    public function renderField($options = [])
    {
        return $this->getInput();
    }

    protected function getInput(): string
    {
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_dharmashop');
        $wa->useScript('com_dharmashop.settings-search');
        $groups = [];
        $seen = [];

        foreach (['category', 'brand', 'badge', 'product'] as $area) {
            foreach (PlaceholderCatalog::describe($area, []) as $group) {
                // Общие коды есть в каждой области; показываем их один раз.
                $key = $group['items'][0]['code'];

                if (!isset($seen[$key])) {
                    $groups[] = $group;
                    $seen[$key] = true;
                }
            }
        }

        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $html = '<aside class="card" data-dharma-seo-shortcodes aria-labelledby="dharma-seo-shortcodes-title">'
            . '<div class="card-header"><h2 id="dharma-seo-shortcodes-title" class="fs-6 fw-semibold mb-0">'
            . $escape(Text::_('COM_DHARMASHOP_PH_TITLE')) . '</h2></div>'
            . '<div class="card-body p-3"><p class="small text-body-secondary">'
            . $escape(Text::_('COM_DHARMASHOP_PH_SETTINGS_NOTE')) . '</p>';

        foreach ($groups as $group) {
            $html .= '<details class="border-top py-2" open><summary class="fw-semibold small">'
                . $escape($group['title']) . '</summary><dl class="mb-0">';

            foreach ($group['items'] as $item) {
                $html .= '<div class="mt-3"><dt class="small fw-normal text-body-secondary mb-1">'
                    . $escape($item['name']) . '</dt><dd class="mb-0"><input type="text" readonly'
                    . ' class="form-control form-control-sm font-monospace" value="' . $escape($item['code'])
                    . '" aria-label="' . $escape($item['name']) . '"></dd></div>';
            }

            $html .= '</dl></details>';
        }

        return $html . '</div></aside>';
    }
}
