<?php
/** Общая точка сборки карточки товара. Здесь будут подключаться её части: слайдер, содержание, цена и действия. */
\defined('_JEXEC') or die;

use Joomla\CMS\Layout\FileLayout;

$folder = (string) ($displayData['cardFolder'] ?? 'product-card');
$folder = in_array($folder, ['product-card', 'brand-product-card', 'badge-product-card'], true) ? $folder : 'product-card';
$variant = (string) ($displayData['cardLayout'] ?? 'grid');
$variant = in_array($variant, ['grid', 'list', 'table'], true) ? $variant : 'grid';
$displayData['imageContext'] ??= (\Joomla\CMS\Factory::getApplication()->getInput()->getCmd('view') === 'search' ? 'search' : 'product.category') . '.' . $variant;
$layout = new FileLayout('components.dharmashop.' . $folder . '.' . $variant, null, ['component' => 'none', 'client' => 0]);

echo $layout->render($displayData);
