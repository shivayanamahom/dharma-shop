<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Image;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** One quality setting for upload conversion, migration and contextual derivatives. */
final class ImageQuality
{
    public const DEFAULT = 82;

    public static function resolve(?Registry $settings = null): int
    {
        $settings ??= ComponentHelper::getParams('com_dharmashop');

        return max(20, min(100, (int) $settings->get('image_quality', self::DEFAULT)));
    }
}
