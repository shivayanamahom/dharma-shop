<?php

/**
 * Dharma Shop — группы полей каталога.
 * @copyright (C) 2026 Dharma.
 * @license GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Controller;

use Joomla\CMS\MVC\Controller\FormController;

\defined('_JEXEC') or die;

/** Группы характеристик: нативные сохранение, отмена и блокировка формы Joomla. */
class FieldgroupController extends FormController
{
    use RequiresActionTrait;

    use QuickCreateCompletionTrait;

    protected $option = 'com_dharmashop';

    protected function requiredAction(): string
    {
        return 'dharmashop.fields.manage';
    }
    protected $text_prefix = 'COM_DHARMASHOP';
    protected $view_list = 'fieldgroups';
}
