<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Support;

use Dharma\Component\DharmaForm\Site\Helper\FormAssets;
use Dharma\Component\DharmaForm\Site\Service\FormService;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Extension\ExtensionHelper;
use Joomla\CMS\Factory;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * Кнопка «Быстрый заказ»: открывает всплывающую форму компонента Dharma Form с товаром. Компонент форм необязателен: без него (или
 * без выбранной всплывающей формы) кнопка не выводится вовсе, чтобы на витрине не оказалось «мёртвой» кнопки.
 *
 * Разметку кнопки выводит общий макет `product-card-parts/actions` (карточки, группы, модули товаров, страница товара); сюда он
 * получает уже готовый ответ `forPlace()`: пустой массив — кнопки нет.
 */
final class QuickOrder
{
    public const COMPONENT = 'com_dharmaform';

    public const SITE_URL = 'https://github.com/shivayanamahom/com_dharma_form';

    public const INSTALL_URL = 'https://github.com/shivayanamahom/com_dharma_form/releases/latest/download/pkg_dharma_form.zip';

    /** Стили кнопки: значение настройки => классы Bootstrap. */
    public const STYLES = [
        'outline-secondary' => 'btn-outline-secondary',
        'outline-primary'   => 'btn-outline-primary',
        'primary'           => 'btn-primary',
        'secondary'         => 'btn-secondary',
        'dark'              => 'btn-dark',
        'link'              => 'btn-link',
    ];

    /** @var array<string, array<string, mixed>> */
    private static array $cache = [];

    /** Компонент Dharma Form установлен, включён и доступен для вызова. */
    public static function installed(): bool
    {
        return ComponentHelper::isEnabled(self::COMPONENT) && class_exists(FormService::class);
    }

    /** Установленная версия Dharma Form (из описания расширения) или пустая строка. */
    public static function version(): string
    {
        $record = ExtensionHelper::getExtensionRecord(self::COMPONENT, 'component');

        return $record ? (string) (new Registry((string) $record->manifest_cache))->get('version', '') : '';
    }

    /** Код формы из настроек: только символы, которые допускает сам Dharma Form. */
    public static function formCode(?Registry $params = null): string
    {
        $params ??= ComponentHelper::getParams('com_dharmashop');

        return (string) preg_replace('~[^A-Za-z0-9._-]+~', '', (string) $params->get('quick_order_form', 'quick-order'));
    }

    /**
     * Данные кнопки для места вывода (`card` — карточки и модули, `page` — страница товара) или пустой массив, если кнопку
     * выводить не нужно. При первом успешном вызове подключается лёгкий загрузчик форм (скрипт самой формы грузится при наведении).
     *
     * @return array{code: string, text: string, class: string, outOfStock: bool}|array{}
     */
    public static function forPlace(string $place): array
    {
        if (isset(self::$cache[$place])) {
            return self::$cache[$place];
        }

        self::$cache[$place] = [];
        $params = ComponentHelper::getParams('com_dharmashop');

        if ((int) $params->get('quick_order_enabled', 0) !== 1 || (int) $params->get('quick_order_' . $place, 1) !== 1 || !self::installed()) {
            return [];
        }

        $code = self::formCode($params);

        // Кнопка нужна, только если форма опубликована, доступна посетителю и всплывающая (обычная форма не «открывается»).
        if ($code === '' || !isset((new FormService())->triggers()[$code])) {
            return [];
        }

        // Скрипты нужны только странице сайта (в консоли, письмах и AJAX-фрагментах документа нет или он не HTML).
        $app = Factory::getApplication();

        if ($app->isClient('site') && method_exists($app, 'getDocument')) {
            FormAssets::enable();
        }

        $style = (string) $params->get('quick_order_style', 'outline-secondary');

        return self::$cache[$place] = [
            'code'       => $code,
            'text'       => trim((string) $params->get('quick_order_text', '')),
            'class'      => self::STYLES[$style] ?? self::STYLES['outline-secondary'],
            'outOfStock' => (int) $params->get('quick_order_out_of_stock', 0) === 1,
        ];
    }
}
