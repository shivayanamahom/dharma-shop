<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Maintenance;

use Dharma\Component\DharmaShop\Administrator\Catalog\Indexer;
use Dharma\Component\DharmaShop\Administrator\Catalog\ProductRebuilder;
use Dharma\Component\DharmaShop\Administrator\Order\OrderRules;
use Dharma\Component\DharmaShop\Administrator\Pricing\SaleScheduler;
use Dharma\Component\DharmaShop\Administrator\Support\Sql;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * Служебные команды обслуживания производных данных. Каждая проходит товары порциями по ключу, укладывается в заданный
 * бюджет времени и возвращает курсор, с которого можно продолжить. Используется командами в админке и (дальше) консольной командой.
 */
final class MaintenanceService
{
    /** Команда => языковой ключ названия. */
    public const COMMANDS = [
        'index' => 'COM_DHARMASHOP_CMD_INDEX',
        'products' => 'COM_DHARMASHOP_CMD_PRODUCTS',
        'sales' => 'COM_DHARMASHOP_CMD_SALES',
    ];

    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly Indexer $indexer,
        private readonly ProductRebuilder $rebuilder,
        private readonly SaleScheduler $sales,
        private readonly OrderRules $rules,
        private readonly int $chunk = 1000,
    ) {
    }

    public static function isCommand(string $name): bool
    {
        return isset(self::COMMANDS[$name]);
    }

    /**
     * @param   string  $command   index | products | sales
     * @param   int     $cursor    id последнего обработанного товара (0 — с начала)
     * @param   int     $budgetMs  сколько миллисекунд можно работать; после очередной порции команда останавливается
     *
     * @return  array{processed: int, cursor: int, done: bool}
     */
    public function run(string $command, int $cursor = 0, int $budgetMs = 8000): array
    {
        if (!self::isCommand($command)) {
            throw new \InvalidArgumentException('Неизвестная команда: ' . $command);
        }

        if ($command === 'sales') {
            return ['processed' => $this->sales->run(), 'cursor' => 0, 'done' => true];
        }

        $start = hrtime(true);
        $processed = 0;

        while (true) {
            $ids = $this->db->setQuery(
                Sql::query($this->db, 'SELECT id FROM #__dharmashop_products WHERE id > :c ORDER BY id', [':c' => [$cursor, ParameterType::INTEGER]]),
                0,
                $this->chunk
            )->loadColumn();

            if (!$ids) {
                if ($command === 'index') {
                    // Строки индекса несуществующих товаров.
                    $this->db->setQuery(
                        'DELETE pc FROM #__dharmashop_product_categories pc LEFT JOIN #__dharmashop_products p ON p.id = pc.product_id WHERE p.id IS NULL'
                    )->execute();
                }

                return ['processed' => $processed, 'cursor' => 0, 'done' => true];
            }

            $from = (int) $ids[0];
            $to = (int) end($ids);

            if ($command === 'index') {
                $this->indexer->rebuildRange($from, $to);
            } else {
                $this->rules->materializeRange($from, $to);
                $this->rebuilder->rebuildRange($from, $to);
                $this->indexer->refreshRange($from, $to);
            }

            $cursor = $to;
            $processed += count($ids);

            if ((hrtime(true) - $start) / 1e6 >= $budgetMs) {
                return ['processed' => $processed, 'cursor' => $cursor, 'done' => false];
            }
        }
    }
}
