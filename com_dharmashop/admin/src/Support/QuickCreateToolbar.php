<?php

/**
 * Joomla toolbar actions for creating product fields without leaving the edit form.
 */
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Support;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\Toolbar;

\defined('_JEXEC') or die;

final class QuickCreateToolbar
{
    public static function add(Toolbar $toolbar, string $taskPrefix, string $formId, bool $canCreate, string $previewUrl = ''): void
    {
        if ($previewUrl !== '') {
            $toolbar->linkButton($taskPrefix . '-preview', 'COM_DHARMASHOP_PREVIEW_ON_SITE')
                ->url($previewUrl)
                ->target('_blank')
                ->attributes(['rel' => 'noopener'])
                ->icon('d-none');
        }

        if (!$canCreate) {
            return;
        }

        $form = htmlspecialchars('#' . $formId, ENT_QUOTES, 'UTF-8');
        $html = '<joomla-toolbar-button task="' . $taskPrefix . '.quickFields" form="' . $form . '" form-validation>'
            . '<button type="button" class="btn btn-outline-primary"><span class="icon-list" aria-hidden="true"></span> '
            . htmlspecialchars(Text::_('COM_DHARMASHOP_QUICK_FIELDS'), ENT_QUOTES, 'UTF-8') . '</button></joomla-toolbar-button>';
        $toolbar->customHtml($html, $taskPrefix . '-quick-fields');
    }
}
