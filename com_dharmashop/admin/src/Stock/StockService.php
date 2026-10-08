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
use Joomla\CMS\Language\Text;
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

    /**
     * Списывает остаток под заказ: атомарно (условие в самом UPDATE, поэтому два одновременных заказа не уведут остаток в минус)
     * и с записью в журнал движений (причина «order»). Вызывается внутри транзакции создания заказа.
     *
     * @throws  \DomainException  если доступного остатка не хватает (и «продавать без остатка» выключено)
     */
    public function consume(int $variantId, float $qty, int $orderId, ?int $userId, bool $allowNegative = false): void
    {
        $location = $this->defaultLocationId();
        $variant = [':v' => [$variantId, ParameterType::INTEGER], ':l' => [$location, ParameterType::INTEGER]];
        Sql::exec($this->db, 'INSERT IGNORE INTO #__dharmashop_stock_levels (variant_id, location_id, qty, reserved) VALUES (:v, :l, 0, 0)', $variant);
        $this->db->setQuery(Sql::query(
            $this->db,
            'UPDATE #__dharmashop_stock_levels SET qty = qty - :q WHERE variant_id = :v AND location_id = :l AND (:neg = 1 OR qty - reserved >= :q2)',
            $variant + [':q' => [$qty], ':q2' => [$qty], ':neg' => [$allowNegative ? 1 : 0, ParameterType::INTEGER]]
        ))->execute();

        if ($this->db->getAffectedRows() < 1) {
            throw new \DomainException(Text::_('COM_DHARMASHOP_CART_ERROR_STOCK'));
        }

        $this->movement($variantId, $location, -$qty, 'order', $orderId, $userId);
        $this->recalcVariant($variantId);
    }

    /** Возвращает остаток на склад (отмена заказа): движение «order_return». */
    public function restore(int $variantId, float $qty, int $orderId, ?int $userId): void
    {
        $location = $this->defaultLocationId();
        Sql::exec(
            $this->db,
            'INSERT INTO #__dharmashop_stock_levels (variant_id, location_id, qty, reserved) VALUES (:v, :l, :q, 0) ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)',
            [':v' => [$variantId, ParameterType::INTEGER], ':l' => [$location, ParameterType::INTEGER], ':q' => [$qty]]
        );
        $this->movement($variantId, $location, $qty, 'order_return', $orderId, $userId);
        $this->recalcVariant($variantId);
    }

    /**
     * Приводит остаток заказа в соответствие со статусом: эффект 1 («возвращает остатки», например «Отменён») возвращает на склад всё, что
     * было списано под заказ; эффект 0 снова списывает возвращённое (если заказ перевели обратно). Работает по журналу движений, поэтому повторный
     * вызов ничего не меняет, а заказы, оформленные до появления списания, остатки не трогают.
     *
     * @throws  \DomainException  если при возврате заказа в работу остатка не хватает
     */
    public function applyOrderEffect(int $orderId, int $effect, ?int $userId): void
    {
        $rows = $this->db->setQuery(Sql::query(
            $this->db,
            "SELECT m.variant_id, SUM(CASE WHEN m.reason = 'order' THEN -m.delta ELSE 0 END) AS consumed, "
            . "SUM(CASE WHEN m.reason = 'order_return' THEN m.delta ELSE 0 END) AS returned, v.backorder "
            . 'FROM #__dharmashop_stock_movements m JOIN #__dharmashop_variants v ON v.id = m.variant_id '
            . "WHERE m.order_id = :o AND m.reason IN ('order', 'order_return') GROUP BY m.variant_id, v.backorder",
            [':o' => [$orderId, ParameterType::INTEGER]]
        ))->loadObjectList();

        foreach ($rows as $row) {
            $net = (float) $row->consumed - (float) $row->returned;

            if ($effect === 1 && $net > 0.0000001) {
                $this->restore((int) $row->variant_id, $net, $orderId, $userId);
            } elseif ($effect === 0 && (float) $row->returned > 0.0000001 && $net < 0.0000001) {
                // Заказ вернули в работу: списываем то, что возвращали, если на складе хватает.
                $this->consume((int) $row->variant_id, (float) $row->returned, $orderId, $userId, (int) $row->backorder === 1);
            }
        }
    }

    private function movement(int $variantId, int $location, float $delta, string $reason, int $orderId, ?int $userId): void
    {
        Sql::exec(
            $this->db,
            'INSERT INTO #__dharmashop_stock_movements (variant_id, location_id, delta, reason, order_id, user_id, created) VALUES (:v, :l, :d, :r, :o, :u, :c)',
            [
                ':v' => [$variantId, ParameterType::INTEGER], ':l' => [$location, ParameterType::INTEGER], ':d' => [$delta], ':r' => [$reason],
                ':o' => [$orderId, ParameterType::INTEGER], ':u' => [$userId, ParameterType::INTEGER], ':c' => [(new Date())->toSql()],
            ]
        );
    }
}
