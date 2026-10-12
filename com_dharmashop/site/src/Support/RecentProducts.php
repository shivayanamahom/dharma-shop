<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Support;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Uri\Uri;

\defined('_JEXEC') or die;

/** Reads a short first-party history of public product IDs. No account data or server endpoint is involved. */
final class RecentProducts
{
    public const COOKIE = 'dharmashop_recent_products';
    public const LIMIT = 20;
    private const LIFETIME = 15552000; // 180 days

    public static function read(CMSApplicationInterface $app): array
    {
        $raw = (string) $app->getInput()->cookie->getString(self::cookieName($app), '');
        $ids = [];
        foreach (explode(',', $raw) as $value) {
            if (ctype_digit($value) && (int) $value > 0) { $ids[(int) $value] = (int) $value; }
            if (count($ids) >= self::LIMIT) { break; }
        }
        return array_values($ids);
    }

    public static function cookieName(CMSApplicationInterface $app): string
    {
        return self::COOKIE . '_' . substr(hash('sha256', rtrim(Uri::root(true), '/')), 0, 8);
    }

    public static function cookiePath(): string
    {
        return rtrim(Uri::root(true), '/') . '/';
    }
}
