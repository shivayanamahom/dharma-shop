<?php

/**
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma
 * @license    GNU General Public License version 2 or later
 */

namespace Dharma\Component\DharmaShop\Administrator\Seo;

use Joomla\CMS\Factory;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * Шаблоны SEO из настроек магазина (вкладка «SEO»). Пустое поле записи = «из компонента», то есть шаблон типа страницы.
 * Подстановки: {global.sitename}; категория — {category.title}, {category.parent}, {category.price_min}, {category.price_max}; бренд — {brand.title};
 * бейдж — {badge.title}; посадочная страница — {landing.title}, {landing.category}, {landing.count}, {landing.introtext}; избранное — {favorites.title}, {favorites.count}; товар — {product.title}, {product.sku}, {product.category}, {product.brand}.
 * Пока настройки не сохранялись, действуют встроенные шаблоны (те же, что значения по умолчанию в config.xml).
 */
final class SeoSettings
{
    public const TYPES = ['category', 'brand', 'badge', 'landing', 'favorites', 'product'];

    public const PARTS = ['title', 'description', 'h1', 'breadcrumb'];

    public function __construct(private readonly Registry $params)
    {
    }

    /** Шаблон части (title, description, h1, breadcrumb) для типа страницы. */
    public function template(string $type, string $part): string
    {
        $raw = $this->params->get('seo_' . $type . '_' . $part);

        if ($raw !== null && $raw !== '') {
            return (string) $raw;
        }

        // Пустое описание — допустимый выбор («без описания»), если оно задано намеренно.
        if ($raw === '' && $part === 'description') {
            return '';
        }

        return self::builtin($type, $part);
    }

    public static function builtin(string $type, string $part): string
    {
        $token = '{' . $type . '.title}';

        return match ($part) {
            'title' => $token . ' | {global.sitename}',
            // Описание страницы с собственным кратким текстом начинается с него (пустой текст вырезается вместе с тире).
            'description' => in_array($type, ['product', 'category', 'landing'], true) ? $token . ' — {' . $type . '.introtext}' : $token . ' — {global.sitename}',
            default => $token,
        };
    }

    /**
     * Значение robots по умолчанию для страниц магазина: настройка магазина, а если выбрано «как в Joomla» (пусто) — общая настройка
     * Joomla («Система → Общие настройки → Метаданные → Роботы»); если и она не задана, `index, follow`. Так закрытие сайта
     * в общих настройках закрывает и магазин.
     */
    public function robots(): string
    {
        $own = trim((string) $this->params->get('seo_robots', ''));

        if ($own !== '') {
            return $own;
        }

        $global = trim((string) Factory::getApplication()->get('robots', ''));

        return $global !== '' ? $global : 'index, follow';
    }

    /** Выводить ли разметку Open Graph. */
    public function openGraph(): bool
    {
        return (int) $this->params->get('seo_opengraph', 1) === 1;
    }

    /** Картинка по умолчанию (для meta image, если у записи нет своей), путь без служебного хвоста или ''. */
    public function defaultImage(): string
    {
        return trim((string) strstr(((string) $this->params->get('seo_default_image', '')) . '#', '#', true));
    }
}
