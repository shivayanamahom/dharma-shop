<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Warehouse;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/** Справочник складов: выборка опубликованных складов с учётом уровня доступа посетителя (ShopServices::warehouses()). */
final class WarehouseService
{
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * Опубликованные склады по идентификаторам в порядке справочника.
     *
     * @param   int[]  $ids
     *
     * @return  Warehouse[]
     */
    public function byIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));

        if (!$ids) {
            return [];
        }

        $identity = Factory::getApplication()->getIdentity();
        $levels = $identity ? array_map('intval', $identity->getAuthorisedViewLevels()) : [1];
        $query = $this->db->createQuery()
            ->select(['id', 'title', 'address', 'phone', 'email', 'hours', 'info', 'ready_term', 'lat', 'lon', 'image'])
            ->from($this->db->quoteName('#__dharmashop_warehouses'))
            ->whereIn('id', $ids)
            ->where('state = 1')
            ->whereIn('access', $levels)
            ->order('ordering, id');
        $rows = (array) $this->db->setQuery($query)->loadObjectList();

        return array_map(static fn (object $row): Warehouse => Warehouse::fromRow($row), $rows);
    }
}
