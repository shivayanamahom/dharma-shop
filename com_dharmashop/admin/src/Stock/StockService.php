<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Stock;

use Dharma\Component\DharmaShop\Administrator\Support\Sql;
use Joomla\CMS\Date\Date;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * Остатки вариантов: уровни по складам, резерв и журнал движений.
 * Остаток меняется только здесь — так журнал и производные поля варианта (stock_total, in_stock) всегда согласованы.
 */
final class StockService
{
    private ?int $defaultLocation = null;

    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    public function defaultLocationId(): int
    {
        if ($this->defaultLocation === null) {
            $id = (int) $this->db->setQuery(
                $this->db->createQuery()->select('id')->from('#__dharmashop_stock_locations')->where('is_default = 1')->order('id')
            )->loadResult();
            $this->defaultLocation = $id > 0 ? $id : 1;
        }

        return $this->defaultLocation;
    }

    /** Остаток варианта на основном складе. */
    public function getQty(int $variantId): float
    {
        $location = $this->defaultLocationId();
        $query = $this->db->createQuery()
            ->select('qty')->from('#__dharmashop_stock_levels')
            ->where('variant_id = :v')->where('location_id = :l')
            ->bind(':v', $variantId, ParameterType::INTEGER)->bind(':l', $location, ParameterType::INTEGER);

        return (float) $this->db->setQuery($query)->loadResult();
    }

    /** Устанавливает остаток основного склада и пишет движение на разницу. */
    public function setQty(int $variantId, float $qty, string $reason = 'manual', ?int $userId = null, ?string $note = null): void
    {
        $location = $this->defaultLocationId();
        $delta = $qty - $this->getQty($variantId);

        Sql::exec(
            $this->db,
            'INSERT INTO #__dharmashop_stock_levels (variant_id, location_id, qty, reserved) VALUES (:v, :l, :q, 0) '
            . 'ON DUPLICATE KEY UPDATE qty = VALUES(qty)',
            [':v' => [$variantId, ParameterType::INTEGER], ':l' => [$location, ParameterType::INTEGER], ':q' => [$qty]]
        );

        if (abs($delta) > 0.0000001) {
            $now = (new Date())->toSql();
            Sql::exec(
                $this->db,
                'INSERT INTO #__dharmashop_stock_movements (variant_id, location_id, delta, reason, user_id, note, created) '
                . 'VALUES (:v, :l, :d, :r, :u, :n, :c)',
                [
                    ':v' => [$variantId, ParameterType::INTEGER], ':l' => [$location, ParameterType::INTEGER], ':d' => [$delta],
                    ':r' => [$reason], ':u' => [$userId, ParameterType::INTEGER], ':n' => [$note], ':c' => [$now],
                ]
            );
        }

        $this->recalcVariant($variantId);
    }

    /** Пересчитывает производные поля варианта: доступно (остаток − резерв) и признак наличия. */
    public function recalcVariant(int $variantId): void
    {
        $sql = 'UPDATE #__dharmashop_variants v SET v.stock_total = COALESCE((SELECT SUM(l.qty - l.reserved) FROM #__dharmashop_stock_levels l WHERE l.variant_id = v.id), 0)'
            . ' WHERE v.id = :v';
        Sql::exec($this->db, $sql, [':v' => [$variantId, ParameterType::INTEGER]]);

        $sql = 'UPDATE #__dharmashop_variants SET in_stock = IF(stock_tracked = 0 OR stock_total > 0 OR backorder = 1, 1, 0) WHERE id = :v';
        Sql::exec($this->db, $sql, [':v' => [$variantId, ParameterType::INTEGER]]);
    }
}
