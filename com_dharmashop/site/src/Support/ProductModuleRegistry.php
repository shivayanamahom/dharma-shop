<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Support;

\defined('_JEXEC') or die;

/** Request-local coordination for optional de-duplication across product-module instances. */
final class ProductModuleRegistry
{
    private static array $products = [];
    private static array $groups = [];

    public static function products(): array { return array_map('intval', array_keys(self::$products)); }
    public static function groups(): array { return array_map('intval', array_keys(self::$groups)); }

    public static function claim(array $items): void
    {
        foreach ($items as $item) {
            $id = (int) ($item->id ?? 0);
            $groupId = (int) ($item->group_id ?? 0);
            if ($id > 0) { self::$products[$id] = true; }
            if ($groupId > 0) { self::$groups[$groupId] = true; }
            if (GroupCardRenderer::isGroup($item) && $groupId > 0) { self::$groups[$groupId] = true; }
        }
    }
}
