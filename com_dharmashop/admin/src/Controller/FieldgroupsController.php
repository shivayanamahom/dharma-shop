<?php

/**
 * Dharma Shop — группы полей каталога.
 * @copyright (C) 2026 Dharma.
 * @license GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Controller;

use Joomla\CMS\MVC\Controller\AdminController;

\defined('_JEXEC') or die;

/** Публикация, корзина, check-in и порядок групп через штатный AdminController. */
class FieldgroupsController extends AdminController
{
    protected $option = 'com_dharmashop';
    protected $text_prefix = 'COM_DHARMASHOP';

    public function getModel($name = 'Fieldgroup', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }
}
