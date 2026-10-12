<?php
/** Shared native Joomla Bootstrap modal for batch and maintenance progress. */
\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

$body = '<div class="modal-body" data-dharmashop-progress-container aria-live="polite"></div>';
$footer = '<button type="button" class="btn btn-outline-secondary" data-progress-dismiss hidden>'
    . htmlspecialchars(Text::_('COM_DHARMASHOP_PROGRESS_DISMISS'), ENT_QUOTES, 'UTF-8') . '</button>'
    . '<button type="button" class="btn btn-primary" data-progress-reload hidden>'
    . htmlspecialchars(Text::_('COM_DHARMASHOP_PROGRESS_RELOAD'), ENT_QUOTES, 'UTF-8') . '</button>';

HTMLHelper::_('bootstrap.modal', '#dharmashop-progress-modal', ['isJoomla' => true]);
echo LayoutHelper::render('libraries.html.bootstrap.modal.main', [
    'selector' => 'dharmashop-progress-modal',
    'params' => [
        'title' => Text::_('COM_DHARMASHOP_PROGRESS_BATCH'),
        'keyboard' => false,
        'backdrop' => 'static',
        'modalWidth' => 70,
        'footer' => $footer,
    ],
    'body' => $body,
]);
