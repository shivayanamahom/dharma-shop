<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Model;

use Dharma\Component\DharmaShop\Administrator\Warehouse\Coords;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;

\defined('_JEXEC') or die;

/** Склад: координаты вводятся одним полем в формате Яндекса и хранятся в столбцах lat/lon. */
class WarehouseModel extends AdminModel
{
    protected $option = 'com_dharmashop';
    protected $text_prefix = 'COM_DHARMASHOP';
    public $typeAlias = 'com_dharmashop.warehouse';

    public function getTable($name = 'Warehouse', $prefix = 'Administrator', $options = []): Table
    {
        return parent::getTable($name, $prefix, $options);
    }

    public function getForm($data = [], $loadData = true): Form|false
    {
        $form = $this->loadForm('com_dharmashop.warehouse', 'warehouse', ['control' => 'jform', 'load_data' => $loadData]);

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
        return Factory::getApplication()->getUserState('com_dharmashop.edit.warehouse.data', []) ?: $this->getItem();
    }

    public function getItem($pk = null)
    {
        $item = parent::getItem($pk);

        if ($item) {
            $item->coords = Coords::format($item->lat !== null ? (float) $item->lat : null, $item->lon !== null ? (float) $item->lon : null);
        }

        return $item;
    }

    public function save($data)
    {
        $data = array_intersect_key((array) $data, array_flip(['id', 'title', 'alias', 'image', 'address', 'phone', 'email', 'hours', 'info', 'ready_term', 'coords', 'state', 'access', 'ordering']));

        if (!$this->getCurrentUser()->authorise('core.edit.state', $this->option)) {
            unset($data['state'], $data['ordering']);
        }

        $coordsRaw = trim((string) ($data['coords'] ?? ''));
        unset($data['coords']);

        if ($coordsRaw === '') {
            $data['lat'] = null;
            $data['lon'] = null;
        } else {
            $coords = Coords::parse($coordsRaw);

            if ($coords === null) {
                $this->setError(Text::_('COM_DHARMASHOP_WAREHOUSE_COORDS_INVALID'));

                return false;
            }

            [$data['lat'], $data['lon']] = $coords;
        }

        return parent::save($data);
    }

    protected function canDelete($record): bool
    {
        return !empty($record->id) && $this->getCurrentUser()->authorise('core.delete', $this->option);
    }
}
