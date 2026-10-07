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

use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;

\defined('_JEXEC') or die;

/**
 * Правка одного товара: добавление, сохранение, отмена — штатная логика FormController.
 */
class ProductController extends FormController
{
    protected $option = 'com_dharmashop';

    protected $text_prefix = 'COM_DHARMASHOP';

    protected $view_list = 'products';

    protected function allowAdd($data = []): bool
    {
        $categoryId = (int) ($data['catid'] ?? $this->input->getInt('filter_category_id'));

        return $categoryId
            ? $this->app->getIdentity()->authorise('core.create', 'com_dharmashop.category.' . $categoryId)
            : parent::allowAdd($data);
    }

    protected function allowEdit($data = [], $key = 'id'): bool
    {
        $recordId = (int) ($data[$key] ?? 0);
        $user = $this->app->getIdentity();

        if ($user->authorise('core.edit', 'com_dharmashop.product.' . $recordId)) {
            return true;
        }

        return $user->authorise('core.edit.own', 'com_dharmashop.product.' . $recordId)
            && (int) ($data['created_by'] ?? 0) === (int) $user->id;
    }

    /** Пакетная обработка выбранных товаров (окно «Пакетная обработка» на списке). */
    public function batch($model = null)
    {
        $this->checkToken();

        $model = $this->getModel('Product', 'Administrator', []);
        $this->setRedirect(Route::_('index.php?option=com_dharmashop&view=products' . $this->getRedirectToListAppend(), false));

        return parent::batch($model);
    }
}
