<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Support;

use Dharma\Component\DharmaShop\Administrator\Pricing\MoneyFormatter;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** Карточка единицы выдачи группы. Все данные подготавливаются пакетно запросом списка; SQL здесь нет. */
final class GroupCardRenderer
{
    private ?ProductCardRenderer $products = null;

    public static function isGroup(object $item): bool
    {
        return ($item->listing_type ?? 'product') === 'group';
    }

    /** listing_group содержит показатели подходящего подмножества, а не общий кеш группы. */
    public function render(object $representative, Registry $params, string $mode, array $fieldColumns = [], ?array $cart = null, string $context = '', string $imageContext = ''): string
    {
        $group = $representative->listing_group ?? null;
        if (!is_object($group) || (int) ($group->id ?? 0) < 1 || (int) ($representative->id ?? 0) < 1) {
            throw new \LogicException('Group listing requires a group and an accessible representative.');
        }
        $mode = in_array($mode, ['grid', 'list', 'table'], true) ? $mode : 'grid';
        $settings = ComponentHelper::getParams('com_dharmashop');
        $money = new MoneyFormatter($settings);
        $ajax = $settings->get('group_card_mode', 'simple') === 'ajax';
        $instance = 'dharma-group-' . bin2hex(random_bytes(6));
        if ($ajax && $context === '') { $context = GroupCardContext::encode($representative->listing_scope ?? [], $params, $mode, $fieldColumns); }
        $range = $money->range($group->price_min ?? null, $group->price_max ?? null, !empty($group->price_free));
        if ((float) ($group->price_min ?? 0) > 0 && (float) ($group->price_max ?? 0) > (float) $group->price_min) {
            $range = Text::sprintf('COM_DHARMASHOP_GROUP_CARD_PRICE_RANGE', $money->format($group->price_min), $money->format($group->price_max));
        }
        $image = trim((string) ($group->cover ?? '')) ?: (string) ($representative->cover ?? '');
        $data = [
            'item' => $representative, 'group' => $group, 'mode' => $mode, 'params' => clone $params,
            'title' => (string) ($group->title ?? ''), 'intro' => (string) ($group->introtext ?? ''),
            'showIntro' => (int) $params->get('product_card_intro', 1) === 1,
            'url' => Route::_('index.php?option=com_dharmashop&view=product&id=' . (int) $representative->id . '&catid=' . (int) $representative->catid, false),
            'image' => $image,
            'imageAlt' => (string) ($group->cover_alt ?? $group->title ?? ''),
            'imageContext' => $imageContext !== '' ? $imageContext : 'group.teaser.' . $mode,
            'productCount' => isset($group->product_count) ? (int) $group->product_count : null,
            'choiceFields' => (array) ($group->choice_fields ?? []),
            'priceRange' => $range,
            'fieldColumns' => $fieldColumns,
            'ajax' => $ajax, 'instance' => $instance, 'context' => $context,
            'selection' => (array) ($representative->group_selection ?? []),
            'ajaxUrl' => Route::_('index.php?option=com_dharmashop&task=groupcard.select&format=json', false),
        ];
        if ($ajax) {
            $document = Factory::getApplication()->getDocument();
            $html = $document instanceof HtmlDocument;
            if ($html) { self::assets(); }
            $this->products ??= new ProductCardRenderer($params, $cart);
            $data['productData'] = $this->products->data($representative, $mode, 'group.teaser', $instance, $html);
            $data['productData']['imageContext'] = $imageContext !== '' ? $imageContext : 'group.teaser.' . $mode;
            $data['productData']['compactActions'] = $mode === 'table';
            $data['image'] = (string) ($data['productData']['images'][0]->url ?? $representative->cover);
            $data['title'] = (string) $representative->title;
            $data['intro'] = (string) $representative->introtext;
        }
        return (new FileLayout('components.dharmashop.group-card.default', null, ['component' => 'none', 'client' => 0]))->render($data);
    }

    /** Load once on the listing, including assets a later selected variant may need. */
    public static function assets(): void
    {
        $document = Factory::getApplication()->getDocument();
        if (!$document instanceof HtmlDocument || ComponentHelper::getParams('com_dharmashop')->get('group_card_mode', 'simple') !== 'ajax') { return; }
        Text::script('COM_DHARMASHOP_GROUP_CARD_ERROR');
        $wa = $document->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_dharmashop');
        $wa->useScript('com_dharmashop.group-card')->useScript('com_dharmashop.sale-countdown')->useScript('com_dharmashop.qty');
        if ($wa->getRegistry()->exists('preset', 'dharmashop.swiper') && ComponentHelper::getParams('com_dharmashop')->get('product_gallery_type', 'swiper') !== 'bootstrap') {
            $wa->usePreset('dharmashop.swiper')->useScript('com_dharmashop.card-slider');
        } else { $wa->useScript('bootstrap.carousel'); }
        if ((int) ComponentHelper::getParams('com_dharmashop')->get('catalog_mode', 0) !== 1) { CartAssets::use($document); }
    }
}
