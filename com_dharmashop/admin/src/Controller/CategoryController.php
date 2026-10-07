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

use Dharma\Component\DharmaShop\Administrator\Support\CategoryTypes;
use Joomla\CMS\MVC\Controller\FormController;

\defined('_JEXEC') or die;

/**
 * Правка одной категории (любого типа: категория, бренд, бейдж). Тип — параметр extension.
 */
class CategoryController extends FormController
{
    protected $option = 'com_dharmashop';

    protected $text_prefix = 'COM_DHARMASHOP';

    protected $view_list = 'categories';

    protected $view_item = 'category';

    protected function allowAdd($data = []): bool
    {
        return $this->app->getIdentity()->authorise('core.create', 'com_dharmashop');
    }

    protected function allowEdit($data = [], $key = 'id'): bool
    {
        $id = (int) ($data[$key] ?? 0);
        $user = $this->app->getIdentity();
        $extension = $this->extensionOf($id, $data);

        if ($user->authorise('core.edit', $extension . '.category.' . $id)) {
            return true;
        }

        return $user->authorise('core.edit.own', $extension . '.category.' . $id) && (int) ($data['created_user_id'] ?? 0) === (int) $user->id;
    }

    protected function getRedirectToItemAppend($recordId = null, $urlVar = 'id'): string
    {
        return parent::getRedirectToItemAppend($recordId, $urlVar) . '&extension=' . $this->extensionOf((int) $recordId);
    }

    protected function getRedirectToListAppend(): string
    {
        return '&extension=' . $this->extensionOf((int) $this->input->getInt('id'));
    }

    /** Тип записи: из запроса/формы, а для существующей записи без указания — из базы. */
    private function extensionOf(int $id, array $data = []): string
    {
        $posted = (array) $this->input->get('jform', [], 'array');
        $extension = (string) ($data['extension'] ?? $posted['extension'] ?? $this->input->getString('extension', ''));

        if (!CategoryTypes::isValid($extension) && $id > 0) {
            $database = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
            $extension = (string) $database->setQuery(
                $database->createQuery()->select('extension')->from('#__categories')->where('id = ' . $id)
            )->loadResult();
        }

        return CategoryTypes::normalize($extension);
    }
}
