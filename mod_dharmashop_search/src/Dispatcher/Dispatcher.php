<?php
declare(strict_types=1);

namespace Dharma\Module\DharmaShopSearch\Site\Dispatcher;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

\defined('_JEXEC') or die;

/**
 * Строка поиска товаров. Форма — обычный GET на страницу результатов (работает без JavaScript); подсказки при вводе подключает
 * внешний скрипт `search.js` (Web Asset Manager), настройки передаются data-атрибутами формы, тексты — через Text::script.
 */
class Dispatcher extends AbstractModuleDispatcher
{
    protected function getLayoutData()
    {
        $data = parent::getLayoutData();
        $params = $data['params'];

        // Страница результатов: выбранный пункт меню или адрес вида «search» (роутер сам найдёт пункт меню «Поиск», если он есть).
        // По умолчанию результаты выводятся на странице каталога (пункт меню «Товары категории» с корнем каталога), а если такого пункта
        // нет — на странице результатов поиска.
        $menuItem = (int) $params->get('menu_item', 0);

        if ($menuItem <= 0) {
            $catalog = $this->getApplication()->getMenu()->getItems('link', 'index.php?option=com_dharmashop&view=category&id=0');
            $menuItem = $catalog ? (int) reset($catalog)->id : 0;
        }

        $url = Route::_($menuItem > 0 ? 'index.php?Itemid=' . $menuItem : 'index.php?option=com_dharmashop&view=search', false);
        $query = (string) parse_url($url, PHP_URL_QUERY);
        parse_str($query, $hidden);

        // GET-форма заменяет строку запроса действия: параметры адреса (не-SEF) передаём скрытыми полями.
        $data['action'] = $query === '' ? $url : substr($url, 0, strpos($url, '?'));
        $data['hidden'] = array_filter($hidden, 'is_scalar');
        // На странице с результатами строка показывает текущий запрос.
        $data['query'] = mb_substr(trim(\Dharma\Component\DharmaShop\Site\Search\ProductSearch::fromInput($this->getApplication()->getInput())), 0, 100);
        $data['suggestUrl'] = Route::_('index.php?option=com_dharmashop&task=search.suggest&format=json', false);
        $data['inputClass'] = $this->classes((string) $params->get('input_class', ''), 'form-control border-0 bg-transparent shadow-none');
        $data['buttonClass'] = $this->classes((string) $params->get('button_class', ''), 'btn border-0 bg-transparent');

        if ((int) $params->get('show_suggestions', 1) === 1) {
            $document = $this->getApplication()->getDocument();
            $wa = $document->getWebAssetManager();
            $wa->getRegistry()->addExtensionRegistryFile('mod_dharmashop_search');
            $wa->useStyle('mod_dharmashop_search.search')->useScript('mod_dharmashop_search.search');

            foreach (['MOD_DHARMASHOP_SEARCH_EMPTY', 'MOD_DHARMASHOP_SEARCH_ALL', 'MOD_DHARMASHOP_SEARCH_ERROR', 'MOD_DHARMASHOP_SEARCH_SKU', 'MOD_DHARMASHOP_SEARCH_OUT_OF_STOCK'] as $key) {
                Text::script($key);
            }
        }

        return $data;
    }

    /** Классы из настроек: только буквы, цифры, «-» и «_»; пусто — значение по умолчанию. */
    private function classes(string $value, string $default): string
    {
        $value = trim((string) preg_replace('/[^A-Za-z0-9_\- ]+/', '', $value));

        return $value !== '' ? (string) preg_replace('/\s+/', ' ', $value) : $default;
    }
}
