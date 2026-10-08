<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Image;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\Event;
\defined('_JEXEC') or die;

/** No catalog queries. A disabled plugin leaves the safe original URL available. */
final class ImageUrl
{
    private static array $resolved = [];
    public static function absolute(string $path): string
    {
        $path = trim(explode('#', $path, 2)[0]);
        if ($path === '' || preg_match('/[\x00-\x20\\\\]/', $path)) return '';
        if (preg_match('~^https?://~i', $path)) return filter_var($path, FILTER_VALIDATE_URL) ? $path : '';
        if (str_starts_with($path, '//') || str_contains($path, ':') || preg_match('~(?:^|/)\.\.(?:/|$)~', $path)) return '';
        return rtrim(Uri::root(), '/') . '/' . ltrim($path, '/');
    }
    public static function resolve(string $source, string $context): string
    {
        $source = self::absolute($source);
        if ($source === '' || !preg_match('/^[a-z][a-z0-9_.-]{0,79}$/D', $context)) return $source;
        $key = $context . "\0" . $source;
        if (isset(self::$resolved[$key])) return self::$resolved[$key];
        $request = new ImageRequest($source, $context);
        try {
            PluginHelper::importPlugin('dharmashop');
            Factory::getApplication()->getDispatcher()->dispatch('onDharmaShopImagePrepare', new Event('onDharmaShopImagePrepare',
                ['request'=>$request, 'settings'=>clone ComponentHelper::getParams('com_dharmashop')]));
        } catch (\Throwable $error) {
            Log::add('Image context ' . $context . ': ' . get_class($error), Log::WARNING, 'com_dharmashop');
        }
        return self::$resolved[$key] = self::absolute($request->url) ?: $source;
    }
}
