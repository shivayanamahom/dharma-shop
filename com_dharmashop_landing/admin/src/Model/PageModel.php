<?php

/**
 * Dharma Shop — посадочные страницы (C85).
 *
 * @package    com_dharmashop_landing
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShopLanding\Administrator\Model;

use Dharma\Component\DharmaShop\Administrator\Filter\FilterService;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Event\Event;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;

\defined('_JEXEC') or die;

/**
 * Посадочная страница. Условия выбираются теми же полями, что в модуле товаров (бренды, бейджи, значения и диапазоны полей, цена,
 * наличие, скидка) и хранятся каноническим массивом по алиасам плюс хэш для поиска страниц с одинаковым составом; ручной список — в `#__dharmashop_landing_products`.
 */
class PageModel extends AdminModel
{
    protected $option = 'com_dharmashop_landing';
    protected $text_prefix = 'COM_DHARMASHOP_LANDING';
    public $typeAlias = 'com_dharmashop_landing.page';

    public function getTable($name = 'Page', $prefix = 'Administrator', $options = []): Table
    {
        return parent::getTable($name, $prefix, $options);
    }

    public function getForm($data = [], $loadData = true): Form|false
    {
        $form = $this->loadForm('com_dharmashop_landing.page', 'page', ['control' => 'jform', 'load_data' => $loadData]);

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
        return Factory::getApplication()->getUserState('com_dharmashop_landing.edit.page.data', []) ?: $this->getItem();
    }

    public function getItem($pk = null)
    {
        $item = parent::getItem($pk);

        if (!$item) {
            return $item;
        }

        $item->listing_mode = (string) (new \Joomla\Registry\Registry((string) ($item->params ?? '')))->get('listing_mode', '');
        $this->conditionsToForm($item, (array) (json_decode((string) ($item->conditions ?? ''), true) ?: []));
        $item->products = [];

        if ((int) $item->id) {
            $db = $this->getDatabase();
            $ids = $db->setQuery(
                $db->createQuery()->select('product_id')->from($db->quoteName('#__dharmashop_landing_products'))
                    ->where('landing_id = ' . (int) $item->id)->order('ordering ASC, product_id ASC')
            )->loadColumn();

            foreach ($ids as $number => $productId) {
                $item->products['products' . $number] = ['product_id' => (int) $productId];
            }
        }

        return $item;
    }

    /** Сохранённые условия (алиасы) → значения полей формы. */
    private function conditionsToForm(object $item, array $conditions): void
    {
        $definition = (new FilterService($this->getDatabase()))->definition(0);
        $byAlias = static function (array $directory, array $aliases): array {
            $ids = [];

            foreach ($directory as $id => $row) {
                if (in_array($row['alias'], $aliases, true)) {
                    $ids[] = (int) $id;
                }
            }

            return $ids;
        };
        $item->cond_brands = $byAlias($definition->brands, (array) ($conditions['brand'] ?? []));
        $item->cond_badges = $byAlias($definition->badges, (array) ($conditions['badge'] ?? []));
        $item->cond_values = [];
        $item->cond_ranges = [];

        foreach ((array) ($conditions['f'] ?? []) as $alias => $value) {
            if (isset($value['min']) || isset($value['max']) || !array_is_list((array) $value)) {
                $item->cond_ranges['cond_ranges' . count($item->cond_ranges)] = ['field' => (string) $alias, 'min' => (string) ($value['min'] ?? ''), 'max' => (string) ($value['max'] ?? '')];

                continue;
            }

            foreach ((array) $value as $option) {
                $item->cond_values[] = $alias . ':' . $option;
            }
        }

        $item->cond_price_min = (string) ($conditions['price']['min'] ?? '');
        $item->cond_price_max = (string) ($conditions['price']['max'] ?? '');
        $item->cond_stock = isset($conditions['stock']) ? 1 : 0;
        $item->cond_sale = isset($conditions['sale']) ? 1 : 0;
    }

    /** Значения полей формы → массив `filter` по алиасам (тот же формат, что в адресе категории). */
    private function conditionsFromForm(array $data): array
    {
        $definition = (new FilterService($this->getDatabase()))->definition(0);
        $filter = [];

        foreach (['brand' => ['cond_brands', $definition->brands], 'badge' => ['cond_badges', $definition->badges]] as $key => [$name, $directory]) {
            foreach (array_map('intval', (array) ($data[$name] ?? [])) as $id) {
                if (isset($directory[$id])) {
                    $filter[$key][] = $directory[$id]['alias'];
                }
            }
        }

        foreach ((array) ($data['cond_values'] ?? []) as $token) {
            if (is_string($token) && preg_match('/^([a-zA-Z0-9_-]{1,190}):([a-zA-Z0-9_-]{1,190})$/D', $token, $match)) {
                $filter['f'][$match[1]][] = $match[2];
            }
        }

        foreach ((array) ($data['cond_ranges'] ?? []) as $row) {
            $row = (array) $row;
            $alias = (string) ($row['field'] ?? '');
            $range = array_filter(['min' => trim((string) ($row['min'] ?? '')), 'max' => trim((string) ($row['max'] ?? ''))], static fn (string $v): bool => $v !== '');

            if ($alias !== '' && $range) {
                $filter['f'][$alias] = $range;
            }
        }

        $price = array_filter(['min' => trim((string) ($data['cond_price_min'] ?? '')), 'max' => trim((string) ($data['cond_price_max'] ?? ''))], static fn (string $v): bool => $v !== '');

        if ($price) {
            $filter['price'] = $price;
        }

        foreach (['stock' => 'cond_stock', 'sale' => 'cond_sale'] as $key => $name) {
            if ((int) ($data[$name] ?? 0) === 1) {
                $filter[$key] = '1';
            }
        }

        return $filter;
    }

    public function save($data)
    {
        $data = array_intersect_key((array) $data, array_flip([
            'id', 'title', 'alias', 'catid', 'source', 'sort', 'product_view', 'cond_brands', 'cond_badges', 'cond_values', 'cond_ranges', 'cond_price_min',
            'cond_price_max', 'cond_stock', 'cond_sale', 'listing_mode', 'products', 'image', 'image_alt', 'introtext',
            'fulltext', 'meta_title', 'meta_description', 'robots', 'state', 'access', 'ordering',
        ]));

        if (!$this->getCurrentUser()->authorise('core.edit.state', $this->option)) {
            unset($data['state'], $data['ordering']);
        }

        $raw = $this->conditionsFromForm($data);
        $products = (array) ($data['products'] ?? []);
        unset($data['cond_brands'], $data['cond_badges'], $data['cond_values'], $data['cond_ranges'], $data['cond_price_min'], $data['cond_price_max'], $data['cond_stock'], $data['cond_sale'], $data['products']);

        $mode = (string) ($data['listing_mode'] ?? '');
        unset($data['listing_mode']);
        $data['params'] = json_encode(in_array($mode, ['products', 'groups_products'], true) ? ['listing_mode' => $mode] : new \stdClass());
        $data['image'] = explode('#', (string) ($data['image'] ?? ''), 2)[0];
        $data['catid'] = (int) ($data['catid'] ?? 0) > 1 ? (int) $data['catid'] : 0;
        $data['conditions'] = '';
        $data['conditions_hash'] = '';
        $data['broken'] = 0;

        if (($data['source'] ?? 'filter') === 'filter') {
            if ($raw) {
                $service = new FilterService($this->getDatabase());
                $definition = $service->definition($data['catid']);
                $state = $service->state($raw, $definition);

                if ($state->isEmpty()) {
                    $this->setError(Text::_('COM_DHARMASHOP_LANDING_CONDITIONS_EMPTY'));

                    return false;
                }

                $canonical = $state->toArray($definition);
                $data['conditions'] = json_encode($canonical, JSON_UNESCAPED_UNICODE);
                $data['conditions_hash'] = sha1($data['catid'] . '|' . $data['conditions']);
            } elseif ($data['catid'] === 0) {
                $this->setError(Text::_('COM_DHARMASHOP_LANDING_NO_SCOPE'));

                return false;
            } else {
                $data['conditions_hash'] = sha1($data['catid'] . '|');
            }
        } else {
            $data['conditions_hash'] = sha1('manual|' . ($data['alias'] ?? ''));
        }

        $productIds = [];

        foreach ($products as $row) {
            $productId = (int) (is_array($row) ? ($row['product_id'] ?? 0) : 0);

            if ($productId > 0) {
                $productIds[$productId] = $productId;
            }
        }

        if (($data['source'] ?? '') === 'manual' && !$productIds) {
            $this->setError(Text::_('COM_DHARMASHOP_LANDING_NO_PRODUCTS'));

            return false;
        }

        if (!parent::save($data)) {
            return false;
        }

        $db = $this->getDatabase();
        $id = (int) $this->getState($this->getName() . '.id');
        $db->setQuery('DELETE FROM ' . $db->quoteName('#__dharmashop_landing_products') . ' WHERE landing_id = ' . $id)->execute();
        $position = 0;

        foreach ($productIds as $productId) {
            $row = (object) ['landing_id' => $id, 'product_id' => $productId, 'ordering' => $position++];
            $db->insertObject('#__dharmashop_landing_products', $row);
        }

        PluginHelper::importPlugin('dharmashop');
        Factory::getApplication()->getDispatcher()->dispatch('onDharmaShopLandingAfterSave', new Event('onDharmaShopLandingAfterSave', ['id' => $id, 'is_new' => empty($data['id']), 'source' => (string) ($data['source'] ?? 'filter')]));

        return true;
    }

    public function delete(&$pks)
    {
        $ids = array_map('intval', (array) $pks);
        $result = parent::delete($pks);

        if ($result && $ids) {
            $db = $this->getDatabase();
            $db->setQuery('DELETE FROM ' . $db->quoteName('#__dharmashop_landing_products') . ' WHERE landing_id IN (' . implode(',', $ids) . ')')->execute();
            PluginHelper::importPlugin('dharmashop');
            Factory::getApplication()->getDispatcher()->dispatch('onDharmaShopLandingAfterDelete', new Event('onDharmaShopLandingAfterDelete', ['ids' => $ids]));
        }

        return $result;
    }

    protected function canDelete($record): bool
    {
        return !empty($record->id) && $this->getCurrentUser()->authorise('core.delete', $this->option);
    }
}
