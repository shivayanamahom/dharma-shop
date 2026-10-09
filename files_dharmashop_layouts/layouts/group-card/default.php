<?php
/** Общая точка вывода группы: режим единого переключателя списка выбирает grid/list/table. */
\defined('_JEXEC') or die;

use Joomla\CMS\Layout\FileLayout;

$mode = (string) ($displayData['mode'] ?? 'grid');
$mode = in_array($mode, ['grid', 'list', 'table'], true) ? $mode : 'grid';
echo (new FileLayout('components.dharmashop.group-card.' . $mode, null, ['component' => 'none', 'client' => 0]))->render($displayData);
