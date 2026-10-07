<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Support;

use Joomla\CMS\Uri\Uri;

\defined('_JEXEC') or die;

final class MediaUrl
{
    /** Убирает служебный хвост Media Joomla, разрешает только локальный путь и HTTP(S). */
    public static function absolute(string $path): string
    {
        $path = trim(explode('#', $path, 2)[0]);
        if ($path === '' || preg_match('/[\x00-\x20\\\\]/', $path)) {
            return '';
        }
        if (preg_match('~^https?://~i', $path)) {
            return filter_var($path, FILTER_VALIDATE_URL) ? $path : '';
        }
        if (str_starts_with($path, '//') || str_contains($path, ':') || preg_match('~(?:^|/)\.\.(?:/|$)~', $path)) {
            return '';
        }

        return rtrim(Uri::root(), '/') . '/' . ltrim($path, '/');
    }
}
