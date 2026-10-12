<?php

/**
 * Dharma Shop — посадочные страницы (C85).
 *
 * @package    com_dharmashop_landing
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShopLanding\Site\Dispatcher;

use Joomla\CMS\Dispatcher\ComponentDispatcher;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

/** Страницы строятся кодом и текстами ядра: ядро запускается и его языковой файл подключается до разбора запроса. */
class Dispatcher extends ComponentDispatcher
{
    public function dispatch(): void
    {
        $this->app->getLanguage()->load('com_dharmashop', JPATH_SITE);
        $this->app->getLanguage()->load('com_dharmashop_landing', JPATH_SITE);

        if (!is_dir(JPATH_SITE . '/components/com_dharmashop')) {
            throw new \RuntimeException(Text::_('COM_DHARMASHOP_LANDING_CORE_MISSING'), 500);
        }

        $this->app->bootComponent('com_dharmashop');
        parent::dispatch();
    }
}
