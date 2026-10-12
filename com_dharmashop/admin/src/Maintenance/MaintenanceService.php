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
use Dharma\Component\DharmaShop\Administrator\Image\ImageWebpMigration;
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
        'webp' => 'COM_DHARMASHOP_CMD_WEBP',
        'facets' => 'COM_DHARMASHOP_CMD_FACETS',
    ];

    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly Indexer $indexer,
        private readonly ProductRebuilder $rebuilder,
        private readonly SaleScheduler $sales,
        private readonly OrderRules $rules,
        private readonly ImageWebpMigration $webp,
        private readonly int $chunk = 1000,
    ) {
    }

    public static function isCommand(string $name): bool
    {
        return isset(self::COMMANDS[$name]);
    }

    /** Число объектов, которые затрагивает команда, для достоверного прогресса в админке. */
    public function countTotal(string $command): int
    {
        if ($command === 'sales') {
            return $this->sales->countPending();
        }

        $sql = match ($command) {
            'index', 'products' => 'SELECT COUNT(*) FROM #__dharmashop_products',
            'facets' => 'SELECT COUNT(DISTINCT catid) FROM (SELECT 0 AS catid UNION SELECT catid FROM #__dharmashop_product_categories UNION SELECT catid FROM #__dharmashop_filter_facets UNION SELECT catid FROM #__dharmashop_filter_ranges) AS categories',
            'webp' => 'SELECT (SELECT COUNT(*) FROM #__dharmashop_products) + (SELECT COUNT(*) FROM #__categories WHERE extension = ' . $this->db->quote('com_dharmashop') . ') + (SELECT COUNT(*) FROM #__dharmashop_field_media WHERE mime IS NULL) + (SELECT COUNT(*) FROM #__dharmashop_group_field_media WHERE mime IS NULL OR (alt <> \'\' AND (mime LIKE \'application/vnd.dharmashop.video.%\' OR mime LIKE \'video/%\' OR mime IN (\'application/mp4\',\'application/ogg\')))) + (SELECT COUNT(*) FROM #__extensions WHERE type = ' . $this->db->quote('component') . ' AND element = ' . $this->db->quote('com_dharmashop') . ')',
            default => throw new \InvalidArgumentException('Unknown maintenance command.'),
        };

        return (int) $this->db->setQuery($sql)->loadResult();
    }

    /**
     * @param   string  $command   index | products | sales
     * @param   int|array  $cursor  курсор товара или этапа обработки изображений
     * @param   int     $budgetMs  сколько миллисекунд можно работать; после очередной порции команда останавливается
     *
     * @return  array{processed: int, cursor: int|array, done: bool, converted?: int, failed?: int}
     */
    public function run(string $command, int|array $cursor = 0, int $budgetMs = 8000): array
    {
        if (!self::isCommand($command)) {
            throw new \InvalidArgumentException('Неизвестная команда: ' . $command);
        }

        if ($command === 'sales') {
            return $this->sales->runStep(is_int($cursor) ? $cursor : 0, 200);
        }

        if ($command === 'facets') {
            // Предрасчёт фильтра (C84): порциями по категориям; курсор — номер последней обработанной категории.
            $step = (new \Dharma\Component\DharmaShop\Administrator\Filter\FacetIndexer($this->db))->rebuildStep(is_int($cursor) ? $cursor : 0, min($budgetMs, 5000));
            \Dharma\Component\DharmaShop\Administrator\Filter\FilterCache::forget();

            return $step;
        }

        if ($command === 'webp') {
            return $this->webp->run(is_array($cursor) ? $cursor : [], min($budgetMs, 5000));
        }

        $cursor = is_int($cursor) ? $cursor : 0;

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
                    // Индекс выдачи групп зависит от строк витрины товаров и дерева категорий (C89).
                    (new \Dharma\Component\DharmaShop\Administrator\Catalog\GroupIndex($this->db))->rebuild(null);
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
                // Полный пересчёт также чинит связи категорий и поисковый индекс; команда index оставляет
                // возможность отдельно восстановить только эти производные таблицы.
                $this->indexer->rebuildRange($from, $to);
            }

            $cursor = $to;
            $processed += count($ids);

            if ((hrtime(true) - $start) / 1e6 >= $budgetMs) {
                return ['processed' => $processed, 'cursor' => $cursor, 'done' => false];
            }
        }
    }
}
