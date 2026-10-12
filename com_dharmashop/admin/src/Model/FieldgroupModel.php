<?php

/**
 * Dharma Shop — группы полей каталога.
 * @copyright (C) 2026 Dharma.
 * @license GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;

\defined('_JEXEC') or die;

/** Группы используют права компонента: таблица не имеет собственного asset_id. */
class FieldgroupModel extends AdminModel
{
    protected $option = 'com_dharmashop';
    protected $text_prefix = 'COM_DHARMASHOP';
    public $typeAlias = 'com_dharmashop.fieldgroup';

    public function getTable($name = 'Fieldgroup', $prefix = 'Administrator', $options = []): Table
    {
        return parent::getTable($name, $prefix, $options);
    }

    public function getForm($data = [], $loadData = true): Form|false
    {
        $form = $this->loadForm('com_dharmashop.fieldgroup', 'fieldgroup', ['control' => 'jform', 'load_data' => $loadData]);

        // Назначение выбирают только при создании; значение берём из записи, а не из POST/сессии.
        $id = (int) (((array) $data)['id'] ?? $this->getState($this->getName() . '.id'));

        if ($form && $id > 0) {
            $table = $this->getTable();

            if ($table->load($id)) {
                $form->setValue('target', null, (string) $table->target);
                $form->setFieldAttribute('target', 'disabled', 'true');
                $form->setFieldAttribute('target', 'required', 'false');
                $form->setFieldAttribute('target', 'filter', 'unset');
            }
        }

        if ($form && !$this->getCurrentUser()->authorise('core.edit.state', $this->option)) {
            foreach (['state', 'ordering'] as $name) {
                $form->setFieldAttribute($name, 'disabled', 'true');
                $form->setFieldAttribute($name, 'filter', 'unset');
            }
        }

        return $form ?: false;
    }

    protected function loadFormData(): mixed
    {
        $data = Factory::getApplication()->getUserState('com_dharmashop.edit.fieldgroup.data', []);

        if (!$data) {
            $data = $this->getItem();

            if ($data && empty($data->id)) {
                $filters = (array) Factory::getApplication()->getUserState('com_dharmashop.fieldgroups.filter', []);
                $selected = (string) ($filters['target'] ?? 'product');
                $data->target = in_array($selected, ['product', 'category'], true) ? $selected : 'product';
                $data->ordering = $this->nextOrdering($data->target);
            }
        }

        return $data;
    }

    protected function prepareTable($table): void
    {
        if (!$table->id && (int) $table->ordering < 1) {
            $table->ordering = $this->nextOrdering((string) $table->target);
        }
    }

    private function nextOrdering(string $target): int
    {
        $db = $this->getDatabase();
        $query = $db->createQuery()->select('MAX(' . $db->quoteName('ordering') . ')')
            ->from($db->quoteName('#__dharmashop_fieldgroups'))
            ->where($db->quoteName('target') . ' = :target')->bind(':target', $target);

        return max(1, (int) $db->setQuery($query)->loadResult() + 1);
    }

    protected function canDelete($record): bool
    {
        return (int) $record->state === -2 && parent::canDelete($record);
    }

    protected function getReorderConditions($table): array
    {
        $db = $this->getDatabase();
        return [$db->quoteName('target') . ' = ' . $db->quote((string) $table->target)];
    }

    protected function cleanCache($group = null)
    {
        parent::cleanCache($group);
        \Dharma\Component\DharmaShop\Administrator\Filter\FilterCache::forget();
    }
}
