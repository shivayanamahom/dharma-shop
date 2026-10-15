<?php
/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Dispatcher;

use Joomla\CMS\Dispatcher\ComponentDispatcher;
use Joomla\CMS\Layout\LayoutHelper;

\defined('_JEXEC') or die;

/**
 * Диспетчер админки магазина. Единственное, что он добавляет к штатному, — подвал с логотипом и версией под каждой страницей:
 * так подвал не нужно подключать в десятках шаблонов страниц. Ответы не в виде страницы (AJAX, файлы, окна) подвал не получают.
 */
class Dispatcher extends ComponentDispatcher
{
    public function dispatch()
    {
        parent::dispatch();

        $document = $this->app->getDocument();

        if (
            $document !== null && $document->getType() === 'html'
            && $this->input->getCmd('format', 'html') === 'html'
            && $this->input->getCmd('tmpl', '') !== 'component'
        ) {
            echo LayoutHelper::render('footer', [], JPATH_ADMINISTRATOR . '/components/com_dharmashop/layouts');
        }
    }
}
