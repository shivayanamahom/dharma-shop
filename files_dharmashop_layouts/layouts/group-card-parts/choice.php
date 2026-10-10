<?php
\defined('_JEXEC') or die;
use Joomla\CMS\Layout\FileLayout;
echo (new FileLayout('components.dharmashop.variant-controls.value', null, ['component' => 'none', 'client' => 0]))->render($displayData + [
    'format' => (string) $displayData['field']->params->get('group_card_format', 'image_title'),
]);
