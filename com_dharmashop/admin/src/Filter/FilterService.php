<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Filter;

use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/** Точка входа фильтра (C84): определения категории, состояние из адреса, условия SQL и выбор формы запроса. Доступна как `ShopServices::filter()`. */
final class FilterService
{
    private ?FilterDefinitionLoader $loader = null;
    private ?FilterQuery $query = null;
    private ?FilterPlanner $planner = null;
    private ?FacetEngine $facets = null;

    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /** Что можно фильтровать в категории для текущего посетителя (кэш). */
    public function definition(int $catid): FilterDefinition
    {
        $this->loader ??= new FilterDefinitionLoader($this->db);

        return $this->loader->forCategory($catid, FilterDefinitionLoader::currentLevels());
    }

    /** Состояние из массива `filter` запроса по справочнику категории. @param array<string, mixed> $filter */
    public function state(array $filter, FilterDefinition $definition): FilterState
    {
        return FilterState::fromArray($filter, $definition);
    }

    public function query(): FilterQuery
    {
        return $this->query ??= new FilterQuery($this->db);
    }

    public function facets(): FacetEngine
    {
        return $this->facets ??= new FacetEngine($this->db);
    }

    public function planner(): FilterPlanner
    {
        return $this->planner ??= new FilterPlanner($this->db);
    }
}
