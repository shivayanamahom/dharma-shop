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

    /** Проверка условий всех страниц: пометка `broken` у страниц, которые каталог больше не понимает. */
    public function check(): void
    {
        $this->checkToken('post');

        if (!$this->app->getIdentity()->authorise('core.edit.state', $this->option)) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $result = (new \Dharma\Component\DharmaShopLanding\Administrator\Maintenance\LandingChecker(\Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class)))->run();
        $this->setMessage(Text::sprintf('COM_DHARMASHOP_LANDING_CHECK_DONE', $result['checked'], $result['broken']), $result['broken'] ? 'warning' : 'message');
        $this->setRedirect(\Joomla\CMS\Router\Route::_('index.php?option=com_dharmashop_landing&view=pages', false));
    }

    public function execute($task)
    {
        if (!$this->app->getIdentity()->authorise('core.manage', $this->option)) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        return parent::execute($task);
    }
}
