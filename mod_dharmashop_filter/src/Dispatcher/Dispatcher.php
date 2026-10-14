<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    mod_dharmashop_filter
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Module\DharmaShopFilter\Site\Dispatcher;

use Dharma\Module\DharmaShopFilter\Site\Helper\FilterHelper;
use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Log\Log;

\defined('_JEXEC') or die;

/**
 * Фильтр товаров (C84). Форма — обычный GET на страницу списка: работает без JavaScript. Данные готовит `FilterHelper` по настройкам модуля;
 * скрипты (AJAX, ползунок, offcanvas и др.) подключаются только если соответствующая возможность включена настройкой: сейчас — `filter.js`
 * при включённом AJAX или применении сразу; иначе страница получает чистую форму без дополнительных ресурсов.
 */
class Dispatcher extends AbstractModuleDispatcher
{
    protected function getLayoutData()
    {
        $data = parent::getLayoutData();

        // Сбой фильтра не должен ронять страницу каталога: модуль просто не выводится, причина — в журнале компонента.
        try {
            $data['filter'] = (new FilterHelper($this->getApplication(), $data['params'], (int) $data['module']->id))->build();
        } catch (\Throwable $error) {
            Log::add('Filter module: ' . $error::class . ': ' . $error->getMessage() . ' @ ' . basename($error->getFile()) . ':' . $error->getLine(), Log::ERROR, 'com_dharmashop');
            $data['filter'] = null;
        }

        // Ресурсы подключаются только для включённых возможностей: фильтр без них — чистая форма без скриптов.
        $params = $data['params'];

        if ($data['filter'] && $this->getApplication()->getInput()->getCmd('task') !== 'filter.refresh') {
            $scripts = [];

            if ($data['filter']['cascade']) { $scripts[] = 'mod_dharmashop_filter.cascade'; }

            if ((int) $params->get('ajax', 0) === 1 || (string) $params->get('apply_mode', 'button') === 'instant') {
                $scripts[] = 'mod_dharmashop_filter.filter';
            }

            if ((string) $params->get('price_display', 'inputs') === 'slider' || (int) $params->get('range_slider', 0) === 1) {
                $scripts[] = 'mod_dharmashop_filter.slider';
            }

            if (array_filter($data['filter']['sections'], static fn(array $s): bool => count($s['options']) >= 10 || !empty($s['limit']))
                || ((string)$params->get('orientation','vertical') === 'horizontal' && (string)$params->get('horizontal_groups','dropdown') === 'dropdown')) { $scripts[] = 'mod_dharmashop_filter.values'; }
            if ((string)$params->get('orientation','vertical') === 'horizontal' && (string)$params->get('horizontal_groups','dropdown') === 'dropdown') { $scripts[] = 'bootstrap.dropdown'; }
            if ((string)$params->get('sticky_mode','off') !== 'off' && (string)$params->get('orientation','vertical') !== 'horizontal' && (string)$params->get('offcanvas_mode','never') !== 'always') { $scripts[] = 'mod_dharmashop_filter.sticky'; }
            if ((int) $params->get('collapsible_groups', 1) === 1) {
                $scripts[] = 'bootstrap.collapse';
            }

            if (in_array((string) $params->get('offcanvas_mode', 'never'), ['mobile', 'always'], true)) {
                $scripts[] = 'bootstrap.offcanvas';
            }

            $styles = (int) $params->get('module_styles', 1) === 1;

            if ($scripts || $styles) {
                try {
                    $wa = $this->getApplication()->getDocument()->getWebAssetManager();
                    $wa->getRegistry()->addExtensionRegistryFile('mod_dharmashop_filter');

                    if (in_array('mod_dharmashop_filter.slider', $scripts, true)) {
                        $wa->useStyle('mod_dharmashop_filter.range');
                    }

                    // Оформление лежит в media модуля; его можно выключить и задать стили в шаблоне сайта. Подключается после стиля ползунка.
                    if ($styles) {
                        $wa->useStyle('mod_dharmashop_filter.styles');
                    }

                    foreach ($scripts as $script) {
                        $wa->useScript($script);
                    }
                } catch (\Throwable $error) {
                    Log::add('Filter module assets: ' . $error::class . ': ' . $error->getMessage(), Log::ERROR, 'com_dharmashop');
                }
            }
        }

        return $data;
    }
}
