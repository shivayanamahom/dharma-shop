<?php

/**
 * Dharma Shop — поля каталога.
 * @copyright (C) 2026 Dharma.
 * @license GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Controller;

use Joomla\CMS\MVC\Controller\AdminController;

\defined('_JEXEC') or die;

/** Публикация, корзина, check-in и порядок групп через штатный AdminController. */
class FieldsController extends AdminController
{
    use RequiresActionTrait;

    protected $option = 'com_dharmashop';

    protected function requiredAction(): string
    {
        return 'dharmashop.fields.manage';
    }
    protected $text_prefix = 'COM_DHARMASHOP';

    public function getModel($name = 'Field', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }
}
