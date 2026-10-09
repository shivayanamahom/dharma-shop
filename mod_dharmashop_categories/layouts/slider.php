<?php
/** Separate override for slider cards; the default appearance follows the module grid card. */
\defined('_JEXEC') or die;
$layout = new \Joomla\CMS\Layout\FileLayout('grid', JPATH_SITE . '/modules/mod_dharmashop_categories/layouts', ['component' => 'none', 'client' => 0]);
$layout->addIncludePath(JPATH_THEMES . '/' . \Joomla\CMS\Factory::getApplication()->getTemplate() . '/html/layouts/modules/mod_dharmashop_categories');
echo $layout->render($displayData);
