<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    plg_task_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Plugin\Task\DharmaShop\Extension;

use Dharma\Component\DharmaShop\Administrator\Catalog\GroupIndex;
use Dharma\Component\DharmaShop\Administrator\Filter\FacetIndexer;
use Dharma\Component\DharmaShop\Administrator\Filter\FilterCache;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status as TaskStatus;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\SubscriberInterface;

\defined('_JEXEC') or die;

/**
 * Задачи планировщика Joomla для Dharma Shop: тонкие обёртки над службами компонента.
 *
 * - `sales`: пересчёт цены у вариантов, чья скидка началась или закончилась (`SaleScheduler::run()`). Без неё стоимость в корзине
 *   меняется только по кнопке «Пересчитать скидки по датам».
 * - `facets`: сверка предрасчёта фильтра с живым подсчётом (`FacetIndexer::verify()`) и индекса выдачи групп (`GroupIndex::verify()`); при расхождениях — полная пересборка.
 */
final class DharmaShopTask extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait;

    /** @var array<string, array<string, string>> */
    protected const TASKS_MAP = [
        'plg_task_dharmashop_sales' => [
            'langConstPrefix' => 'PLG_TASK_DHARMASHOP_SALES',
            'method' => 'runSales',
        ],
        'plg_task_dharmashop_facets' => [
            'langConstPrefix' => 'PLG_TASK_DHARMASHOP_FACETS',
            'method' => 'verifyFacets',
        ],
    ];

    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList' => 'advertiseRoutines',
            'onExecuteTask' => 'standardRoutineHandler',
        ];
    }

    protected function runSales(ExecuteTaskEvent $event): int
    {
        $this->logTask('Dharma Shop: пересчёт скидок по датам');
        $count = Factory::getApplication()->bootComponent('com_dharmashop')->getServices()->sales()->run();
        $this->logTask('Dharma Shop: обработано товаров ' . $count);

        return TaskStatus::OK;
    }

    protected function verifyFacets(ExecuteTaskEvent $event): int
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $indexer = new FacetIndexer($db);
        $bad = $indexer->verify(10000);

        if ($bad) {
            $this->logTask('Dharma Shop: расхождений предрасчёта фильтра ' . count($bad) . ', выполняется пересборка');
            $indexer->rebuildAll();
            FilterCache::forget();
        } else {
            $this->logTask('Dharma Shop: предрасчёт фильтра согласован');
        }

        // Индекс выдачи групп (C89): строки по категориям, ключи значений и границы.
        $groups = new GroupIndex($db);
        $groupBad = $groups->verify(100000);

        if ($groupBad) {
            $this->logTask('Dharma Shop: расхождений индекса групп ' . $groupBad . ', выполняется пересборка');
            $groups->rebuild(null);
        } else {
            $this->logTask('Dharma Shop: индекс групп согласован');
        }

        return TaskStatus::OK;
    }
}
