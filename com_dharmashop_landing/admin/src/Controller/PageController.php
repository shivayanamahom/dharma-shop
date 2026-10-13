<?php

/**
 * Dharma Shop — посадочные страницы (C85).
 *
 * @package    com_dharmashop_landing
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShopLanding\Administrator\Controller;

use Joomla\CMS\MVC\Controller\FormController;

\defined('_JEXEC') or die;

/** Страница: нативные сохранение, отмена и блокировка формы Joomla. */
class PageController extends FormController
{
    protected $option = 'com_dharmashop_landing';
    protected $text_prefix = 'COM_DHARMASHOP_LANDING';
    protected $view_list = 'pages';
}
