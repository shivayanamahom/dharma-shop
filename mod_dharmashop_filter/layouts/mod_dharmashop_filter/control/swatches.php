<?php
\defined('_JEXEC') or die;
$layout = new \Joomla\CMS\Layout\FileLayout('mod_dharmashop_filter.control.options');
$layout->addIncludePath(JPATH_ROOT . '/modules/mod_dharmashop_filter/layouts');
echo $layout->render($displayData + ['control' => 'swatches']);
