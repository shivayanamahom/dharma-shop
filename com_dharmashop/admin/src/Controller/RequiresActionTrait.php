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

\defined('_JEXEC') or die;

/**
 * Любая задача контроллера требует отдельного действия прав (`dharmashop.fields.manage`, `dharmashop.groups.manage`…): структура каталога
 * (поля, группы полей, группы товаров) доверяется отдельной роли, а не каждому, кто может править товары. Проверка стоит на входе
 * (`execute`), до разбора задачи, поэтому её не обойти прямым адресом.
 */
trait RequiresActionTrait
{
    /** Действие из `access.xml`, которое нужно для любой задачи контроллера. */
    abstract protected function requiredAction(): string;

    public function execute($task)
    {
        if (!$this->app->getIdentity()->authorise($this->requiredAction(), 'com_dharmashop')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        return parent::execute($task);
    }
}
