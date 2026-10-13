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

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;

\defined('_JEXEC') or die;

/** Публикация, порядок и удаление страниц через штатный AdminController. */
class PagesController extends AdminController
{
    protected $option = 'com_dharmashop_landing';
    protected $text_prefix = 'COM_DHARMASHOP_LANDING';

    public function getModel($name = 'Page', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    public function execute($task)
    {
        if (!$this->app->getIdentity()->authorise('core.manage', $this->option)) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        return parent::execute($task);
    }
}
