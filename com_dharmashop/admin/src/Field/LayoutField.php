<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Field;

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

/** Выбор только тех макетов, PHP-файлы которых существуют в папке соответствующего слота витрины. */
class LayoutField extends ListField
{
    protected $type = 'Layout';

    /** Слот формы => папка относительно корня Joomla: страницы компонента и общие JLayout. */
    private const LAYOUT_DIRECTORIES = [
        'product' => 'components/com_dharmashop/tmpl/product',
        'category' => 'components/com_dharmashop/tmpl/category',
        'categories' => 'components/com_dharmashop/tmpl/categories',
        'category_card' => 'layouts/components/dharmashop/category-card',
        'product_card' => 'layouts/components/dharmashop/product-card',
        'product_card_variant' => 'layouts/components/dharmashop/product-card',
        'brand' => 'components/com_dharmashop/tmpl/brand',
        'badge' => 'components/com_dharmashop/tmpl/badge',
        'brand_product_card' => 'layouts/components/dharmashop/brand-product-card',
        'badge_product_card' => 'layouts/components/dharmashop/badge-product-card',
        'brands' => 'components/com_dharmashop/tmpl/brands',
        'badges' => 'components/com_dharmashop/tmpl/badges',
        'brand_card' => 'layouts/components/dharmashop/brand-card',
        'badge_card' => 'layouts/components/dharmashop/badge-card',
    ];

    protected function getOptions(): array
    {
        $group = (string) ($this->element['layoutgroup'] ?? '');
        $directory = self::LAYOUT_DIRECTORIES[$group] ?? null;
        $files = $directory ? glob(JPATH_ROOT . '/' . $directory . '/*.php') : [];
        $layouts = [];

        foreach ($files ?: [] as $file) {
            if (!is_file($file)) {
                continue;
            }

            $filename = basename($file);

            if (!preg_match('/^([A-Za-z0-9_-]+)\.php$/D', $filename, $matches)) {
                continue;
            }

            // В папках страниц файлы вида default_items.php — части страницы (loadTemplate), а не самостоятельные макеты.
            if (str_starts_with($directory, 'components/com_dharmashop/tmpl/') && str_contains($matches[1], '_')) {
                continue;
            }

            if ($group === 'product_card_variant' && $matches[1] === 'default') {
                continue;
            }

            $layouts[$matches[1]] = $filename;
        }

        ksort($layouts, SORT_NATURAL | SORT_FLAG_CASE);

        if ($directory === null) {
            return parent::getOptions();
        }

        // Не скрываем уже сохранённое значение, если соответствующий файл временно удалили.
        $current = (string) $this->value;

        if ($current !== '' && preg_match('/^[A-Za-z0-9_-]+$/D', $current) && !isset($layouts[$current])) {
            $layouts[$current] = Text::sprintf('COM_DHARMASHOP_LAYOUT_MISSING_FILE', $current . '.php');
        }

        $options = [];

        foreach ($layouts as $value => $filename) {
            $options[] = HTMLHelper::_('select.option', $value, $filename);
        }

        if (!$layouts && $this->value === null) {
            $options[] = HTMLHelper::_('select.option', '', Text::_('COM_DHARMASHOP_LAYOUT_NO_FILES'), 'value', 'text', true);
        }

        return array_merge(parent::getOptions(), $options);
    }
}
