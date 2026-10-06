<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Controller;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;
use Joomla\Utilities\ArrayHelper;

\defined('_JEXEC') or die;

/**
 * Операции над списком товаров: публикация, архив, корзина, удаление, «рекомендуемый».
 */
class ProductsController extends AdminController
{
    protected $option = 'com_dharmashop';

    protected $text_prefix = 'COM_DHARMASHOP';

    public function getModel($name = 'Product', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    /** Включает или снимает отметку «рекомендуемый» у выбранных товаров (задачи featured / unfeatured). */
    public function featured(): void
    {
        $this->checkToken();

        $ids = (array) $this->input->get('cid', [], 'array');
        $value = $this->getTask() === 'featured' ? 1 : 0;
        $ids = ArrayHelper::toInteger($ids);

        if (!$ids) {
            $this->app->enqueueMessage(Text::_('JGLOBAL_NO_ITEM_SELECTED'), 'warning');
        } elseif ($this->getModel()->featured($ids, $value)) {
            $this->setMessage(Text::sprintf($value ? 'COM_DHARMASHOP_N_ITEMS_FEATURED' : 'COM_DHARMASHOP_N_ITEMS_UNFEATURED', count($ids)));
        }

        $this->setRedirect(Route::_('index.php?option=com_dharmashop&view=products', false));
    }
}
