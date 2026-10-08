<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Support;

\defined('_JEXEC') or die;

/**
 * Тип записей витрины: категория, бренд или бейдж. Страницы товаров этих трёх типов устроены одинаково (список товаров по индексу
 * `product_categories`), различаются раздел `#__categories`, имена видов и ключи настроек.
 */
final class Taxonomy
{
    private function __construct(
        /** category, brand или badge: область подстановок и SEO, префикс ключей карточек. */
        public readonly string $area,
        /** Раздел категорий Joomla. */
        public readonly string $extension,
        /** Вид списка (меню): categories, brands, badges. */
        public readonly string $listView,
        /** Вид страницы записи с товарами: category, brand, badge. */
        public readonly string $pageView,
        /** Ключ параметров компонента с данными корневой страницы (пусто — корня нет). */
        public readonly string $rootParam,
        /** Языковая строка названия корня по умолчанию. */
        public readonly string $rootTitle,
    ) {
    }

    public static function category(): self
    {
        return new self('category', 'com_dharmashop', 'categories', 'category', 'root_category', 'COM_DHARMASHOP_CATALOG');
    }

    /**
     * Результаты поиска: карточки, колонки и настройки отображения — как у каталога (корень категорий), страница — вид `search`.
     */
    public static function search(): self
    {
        return new self('category', 'com_dharmashop', 'categories', 'search', 'root_category', 'COM_DHARMASHOP_SEARCH');
    }

    public static function brand(): self
    {
        return new self('brand', 'com_dharmashop.brand', 'brands', 'brand', 'root_brand', 'COM_DHARMASHOP_BRANDS');
    }

    public static function badge(): self
    {
        return new self('badge', 'com_dharmashop.badge', 'badges', 'badge', '', 'COM_DHARMASHOP_BADGES');
    }

    /** Ключ параметров: колонки списка записей (categories_columns, brands_columns…). */
    public function gridPrefix(): string
    {
        return $this->listView;
    }

    /** Ключи карточки записи в списке: {area}_card_layout, _intro, _count, _prices. */
    public function cardKey(string $suffix): string
    {
        return $this->area . '_card_' . $suffix;
    }

    /** Ключ выбора макета списка записей (categories_layout, brands_layout…). */
    public function listLayoutKey(): string
    {
        return $this->listView . '_layout';
    }

    /** Папка карточек записи в общей библиотеке. */
    public function cardFolder(): string
    {
        return $this->area . '-card';
    }
}
