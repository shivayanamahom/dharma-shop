<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Catalog;

use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Dharma\Component\DharmaShop\Administrator\Support\Sql;

\defined('_JEXEC') or die;

/** Разрешает положение нового товара по цепочке категория → родители → общие настройки. */
final class CategoryPositionRules
{
    public function __construct(private readonly DatabaseInterface $db, private readonly string $globalPosition)
    {
    }

    /** @return array{value: string, source: int|string} */
    public function forCategory(int $categoryId, bool $includeSelf = true): array
    {
        $chain = $this->chain($categoryId);

        if (!$includeSelf && $chain) {
            array_shift($chain);
        }

        $value = $this->defaultPosition();
        $source = 'settings';

        foreach (array_reverse($chain) as $row) {
            $own = $this->validPosition($row['position']);

            if ($own !== null) {
                $value = $own;
                $source = $row['id'];
            }
        }

        return ['value' => $value, 'source' => $source];
    }

    /** @param int[] $categoryIds @return array<int, string> Положение каждого выбранного раздела и его родителей. */
    public function forCategories(array $categoryIds): array
    {
        $result = [];

        foreach (array_values(array_unique(array_filter(array_map('intval', $categoryIds)))) as $categoryId) {
            $value = $this->defaultPosition();

            foreach (array_reverse($this->chain($categoryId)) as $row) {
                $own = $this->validPosition($row['position']);

                if ($own !== null) {
                    $value = $own;
                }

                $result[$row['id']] = $value;
            }
        }

        return $result;
    }

    public function sourceLabel(int|string $source): string
    {
        if (!is_int($source)) {
            return Text::_('COM_DHARMASHOP_ORDER_FROM_SETTINGS');
        }

        $title = (string) $this->db->setQuery(
            $this->db->createQuery()->select('title')->from('#__categories')->where('id = ' . $source)
        )->loadResult();

        return Text::sprintf('COM_DHARMASHOP_ORDER_FROM_CATEGORY', $title);
    }

    private function defaultPosition(): string
    {
        return $this->validPosition($this->globalPosition) ?? 'last';
    }

    private function validPosition(mixed $position): ?string
    {
        $position = (string) $position;

        return in_array($position, ['first', 'last'], true) ? $position : null;
    }

    /** @return array<int, array{id: int, position: mixed}> Ближайшая категория первой. */
    private function chain(int $categoryId): array
    {
        if ($categoryId <= 1) {
            return [];
        }

        $query = Sql::query(
            $this->db,
            'SELECT c.id, c.params FROM #__categories c '
            . 'JOIN #__categories selected ON selected.id = :id '
            . 'WHERE c.extension = selected.extension AND c.extension = \'com_dharmashop\' '
            . 'AND c.lft <= selected.lft AND c.rgt >= selected.rgt ORDER BY c.lft DESC',
            [':id' => [$categoryId, ParameterType::INTEGER]]
        );
        $chain = [];

        foreach ($this->db->setQuery($query)->loadObjectList() as $row) {
            $params = json_decode((string) $row->params, true) ?: [];
            $chain[] = ['id' => (int) $row->id, 'position' => $params['new_product_position'] ?? ''];
        }

        return $chain;
    }
}
