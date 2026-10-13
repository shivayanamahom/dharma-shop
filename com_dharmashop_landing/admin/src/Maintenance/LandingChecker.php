<?php

/**
 * Dharma Shop — посадочные страницы (C85).
 *
 * @package    com_dharmashop_landing
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShopLanding\Administrator\Maintenance;

use Dharma\Component\DharmaShop\Administrator\Filter\FilterService;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\Event;

\defined('_JEXEC') or die;

/**
 * Проверка посадочных страниц: условия страницы больше не распознаются каталогом (удалён бренд, бейдж, поле или значение, поле снято
 * с публикации), категория-источник закрыта или удалена, в ручном списке не осталось доступных товаров. Такая страница помечается
 * `broken`: на сайте она отдаёт 404, в список страниц и в редирект со страницы фильтра не попадает; в админке видна пометка.
 */
final class LandingChecker
{
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * Пересчитывает пометку `broken`; меняет только те строки, у которых она изменилась.
     *
     * @return  array{checked: int, broken: int, newly_broken: int[], recovered: int[]}
     */
    public function run(?int $onlyId = null): array
    {
        $db = $this->db;
        $query = $db->createQuery()->select(['id', 'catid', 'source', 'conditions', 'broken'])->from($db->quoteName('#__dharmashop_landing_pages'));

        if ($onlyId !== null) {
            $query->where('id = ' . (int) $onlyId);
        }

        $service = new FilterService($db);
        $newly = $recovered = [];
        $total = $broken = 0;

        foreach ($db->setQuery($query)->loadObjectList() as $page) {
            ++$total;
            $isBroken = $this->isBroken($page, $service);
            $broken += $isBroken ? 1 : 0;

            if ($isBroken !== ((int) $page->broken === 1)) {
                $db->setQuery('UPDATE ' . $db->quoteName('#__dharmashop_landing_pages') . ' SET broken = ' . ($isBroken ? 1 : 0) . ' WHERE id = ' . (int) $page->id)->execute();
                $isBroken ? $newly[] = (int) $page->id : $recovered[] = (int) $page->id;
            }
        }

        if ($newly || $recovered) {
            PluginHelper::importPlugin('dharmashop');
            Factory::getApplication()->getDispatcher()->dispatch('onDharmaShopLandingChecked', new Event('onDharmaShopLandingChecked', ['newly_broken' => $newly, 'recovered' => $recovered]));
        }

        return ['checked' => $total, 'broken' => $broken, 'newly_broken' => $newly, 'recovered' => $recovered];
    }

    private function isBroken(object $page, FilterService $service): bool
    {
        $db = $this->db;
        $catid = (int) $page->catid;

        if ($catid > 1 && !(int) $db->setQuery('SELECT COUNT(*) FROM ' . $db->quoteName('#__categories') . ' WHERE id = ' . $catid . ' AND published = 1 AND extension = ' . $db->quote('com_dharmashop'))->loadResult()) {
            return true;
        }

        if ((string) $page->source === 'manual') {
            return !(int) $db->setQuery(
                'SELECT COUNT(*) FROM ' . $db->quoteName('#__dharmashop_landing_products', 'lp') . ' JOIN ' . $db->quoteName('#__dharmashop_products', 'p') . ' ON p.id = lp.product_id AND p.state = 1 WHERE lp.landing_id = ' . (int) $page->id
            )->loadResult();
        }

        $stored = (array) (json_decode((string) $page->conditions, true) ?: []);

        return $stored !== [] && self::conditionsChanged($service, $catid, $stored);
    }

    /** Условия, сохранённые на странице, каталог сегодня разбирает иначе (часть значений не найдена) или не разбирает вовсе. @param array<string, mixed> $stored */
    public static function conditionsChanged(FilterService $service, int $catid, array $stored): bool
    {
        $definition = $service->definition($catid > 1 ? $catid : 0);
        $state = $service->state($stored, $definition);

        return $state->isEmpty() || json_encode($state->toArray($definition), JSON_UNESCAPED_UNICODE) !== json_encode($stored, JSON_UNESCAPED_UNICODE);
    }
}
