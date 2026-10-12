<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Support;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Layout\FileLayout;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** Общие параметры поведения избранного и карточек страницы списка. */
final class FavoritesSettings
{
    public static function enabled(): bool
    {
        return (int) ComponentHelper::getParams('com_dharmashop')->get('favorites_enabled', 1) === 1;
    }

    public static function cardLayoutSource(): string
    {
        $source = (string) ComponentHelper::getParams('com_dharmashop')->get('favorites_card_layout_source', 'component');

        return in_array($source, ['component', 'override'], true) ? $source : 'component';
    }

    public static function cardLayout(): string
    {
        $layout = (string) ComponentHelper::getParams('com_dharmashop')->get('favorites_card_layout', 'grid');

        return in_array($layout, ['grid', 'list', 'table'], true) ? $layout : 'grid';
    }

    public static function columns(): int
    {
        $columns = (int) ComponentHelper::getParams('com_dharmashop')->get('favorites_columns', 3);

        return max(1, min(6, $columns));
    }

    public static function gridClasses(?Registry $menuParams = null): string
    {
        $params = clone ComponentHelper::getParams('com_dharmashop');
        if ($menuParams !== null) {
            foreach (Grid::keys('favorites') as $key) {
                $value = $menuParams->get($key);
                if ($value !== null && $value !== '') {
                    $params->set($key, $value);
                }
            }
        }

        return Grid::classes($params, 'favorites', 'g-3');
    }

    public static function renderIcon(int $size = 20): string
    {
        return (new FileLayout('components.dharmashop.favorites.icon', null, ['component' => 'none', 'client' => 0]))
            ->render(['size' => max(12, min(48, $size))]);
    }
}
