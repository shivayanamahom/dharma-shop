<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Placeholder;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** Общие значения подстановок магазина. */
final class GlobalValues
{
    /** @return array<string, string> */
    public static function build(Registry $settings): array
    {
        $app = Factory::getApplication();

        return [
            'global.sitename' => (string) $app->get('sitename'),
            'global.currency' => (string) $settings->get('currency_symbol', '₽'),
            'global.url' => rtrim(Uri::root(), '/') . '/',
        ];
    }
}
