<?php
/** Close a quick-create dialog and refresh its saved parent form. */
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Controller;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Router\Route;

\defined('_JEXEC') or die;

trait QuickCreateCompletionTrait
{
    protected function postSaveHook(BaseDatabaseModel $model, $validData = [])
    {
        parent::postSaveHook($model, $validData);

        if ($this->input->getBool('quick_create') && $this->getTask() === 'save') {
            $savedId = (int) $model->getState($model->getName() . '.id');
            if ($savedId > 0) {
                // Joomla computes the regular list redirect before invoking postSaveHook.
                $this->setRedirect(Route::_('index.php?option=com_dharmashop&view=' . $this->getName()
                    . '&layout=created&id=' . $savedId . '&tmpl=component', false));
            }
        }
    }

    protected function getRedirectUrlToList(): string
    {
        if ($this->input->getBool('quick_create')) {
            $target = $this->input->getCmd('quick_target', 'product') === 'category' ? 'category' : 'product';
            $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $this->input->getString('quick_fields', ''))))));
            return Route::_('index.php?option=com_dharmashop&view=quickfields&layout=modal&tmpl=component'
                . '&target=' . $target . '&field_ids=' . implode(',', $ids), false);
        }

        return parent::getRedirectUrlToList();
    }

    protected function getRedirectToItemAppend($recordId = null, $urlVar = 'id')
    {
        $append = parent::getRedirectToItemAppend($recordId, $urlVar);

        if ($this->input->getBool('quick_create')) {
            $append .= '&quick_create=1&quick_target=' . $this->input->getCmd('quick_target', 'product')
                . '&quick_fields=' . rawurlencode($this->input->getString('quick_fields', ''));
        }

        return $append;
    }
}
