<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Support;

use Dharma\Component\DharmaShop\Administrator\Pricing\MoneyFormatter;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** Карточка единицы выдачи группы. Все данные подготавливаются пакетно запросом списка; SQL здесь нет. */
final class GroupCardRenderer
{
    public static function isGroup(object $item): bool
    {
        return ($item->listing_type ?? 'product') === 'group';
    }

    /** listing_group содержит показатели подходящего подмножества, а не общий кеш группы. */
    public function render(object $representative, Registry $params, string $mode, array $fieldColumns = []): string
    {
        $group = $representative->listing_group ?? null;
        if (!is_object($group) || (int) ($group->id ?? 0) < 1 || (int) ($representative->id ?? 0) < 1) {
            throw new \LogicException('Group listing requires a group and an accessible representative.');
        }
        $mode = in_array($mode, ['grid', 'list', 'table'], true) ? $mode : 'grid';
        $money = new MoneyFormatter(ComponentHelper::getParams('com_dharmashop'));
        $image = trim((string) ($group->cover ?? '')) ?: (string) ($representative->cover ?? '');
        $data = [
            'item' => $representative, 'group' => $group, 'mode' => $mode, 'params' => clone $params,
            'title' => (string) ($group->title ?? ''), 'intro' => (string) ($group->introtext ?? ''),
            'showIntro' => (int) $params->get('product_card_intro', 1) === 1,
            'url' => Route::_('index.php?option=com_dharmashop&view=product&id=' . (int) $representative->id . '&catid=' . (int) $representative->catid, false),
            'image' => $image,
            'imageAlt' => (string) ($group->cover_alt ?? $group->title ?? ''),
            'imageContext' => 'group.teaser.' . $mode,
            'productCount' => isset($group->product_count) ? (int) $group->product_count : null,
            'choiceFields' => (array) ($group->choice_fields ?? []),
            'priceRange' => $money->range($group->price_min ?? null, $group->price_max ?? null, !empty($group->price_free)),
            'fieldColumns' => $fieldColumns,
        ];
        return (new FileLayout('components.dharmashop.group-card.default', null, ['component' => 'none', 'client' => 0]))->render($data);
    }
}
