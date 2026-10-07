<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Model;

use Dharma\Component\DharmaShop\Administrator\Service\ShopServices;
use Dharma\Component\DharmaShop\Administrator\Support\CategoryTypes;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * Товар: карточка + вариант по умолчанию (цена, артикул, остаток) + категории + витринный индекс — одним сохранением.
 */
class ProductModel extends AdminModel
{
    public $typeAlias = 'com_dharmashop.product';

    protected $text_prefix = 'COM_DHARMASHOP';

    /**
     * Пакетные операции: блок окна «Пакетная обработка» (forms/batch_product.xml) => метод. Блок передаёт ['action' => …, 'value' => …].
     * Состав расширяется: блок в forms/batch_product.xml, строка сюда и метод batch<Имя>. Копирование/перемещение штатной «категории» не используем.
     */
    protected $batch_copymove = '';

    protected $batch_commands = [
        'category' => 'batchMoveCategory',
        'extra_categories' => 'batchExtraCategories',
        'brand' => 'batchBrand',
        'badges' => 'batchBadges',
    ];

    /** Поля формы, которые относятся к варианту, а не к карточке. */
    private const VARIANT_FIELDS = ['sku', 'stock_tracked', 'backorder', 'weight', 'length', 'width', 'height', 'price', 'sale_price', 'sale_start', 'sale_end'];

    public function getTable($name = 'Product', $prefix = 'Administrator', $options = []): Table
    {
        return parent::getTable($name, $prefix, $options);
    }

    private function shop(): ShopServices
    {
        return Factory::getApplication()->bootComponent('com_dharmashop')->getServices();
    }

    public function getForm($data = [], $loadData = true): Form|false
    {
        $form = $this->loadForm('com_dharmashop.product', 'product', ['control' => 'jform', 'load_data' => $loadData]);

        return empty($form) ? false : $form;
    }

    protected function loadFormData(): mixed
    {
        $data = Factory::getApplication()->getUserState('com_dharmashop.edit.product.data', []);

        if (empty($data)) {
            $data = $this->getItem();

            if (!$data) {
                return [];
            }

            if (empty($data->id)) {
                $data->catid = (int) Factory::getApplication()->getInput()->getInt('filter_category_id', $data->catid ?? 0);
            }
        }

        return $data;
    }

    public function getItem($pk = null)
    {
        $item = parent::getItem($pk);

        if (!$item) {
            return $item;
        }

        $item->metadata = (new Registry($item->metadata ?? ''))->toArray();
        $item->extra_catids = [];
        $item->brand_id = 0;
        $item->badge_ids = [];
        $item->sku = $item->price = $item->sale_price = $item->sale_start = $item->sale_end = null;
        $item->stock_tracked = $item->backorder = 0;
        $item->stock_qty = 0;
        $item->weight = $item->length = $item->width = $item->height = null;

        if (!empty($item->id)) {
            $shop = $this->shop();
            $taxonomy = $shop->indexer()->getTaxonomy((int) $item->id);
            $item->extra_catids = $taxonomy['extra'];
            $item->brand_id = $taxonomy['brand'];
            $item->badge_ids = $taxonomy['badges'];

            if ($variant = $shop->variants()->getDefault((int) $item->id)) {
                foreach (self::VARIANT_FIELDS as $field) {
                    $item->$field = $variant->$field;
                }

                $item->stock_qty = $shop->stock()->getQty((int) $variant->id);
            }
        }

        return $item;
    }

    /**
     * Сохранение в три шага. Карточку нельзя оборачивать в транзакцию: Table::store() у Joomla для прав берёт LOCK TABLES,
     * а это неявно фиксирует транзакцию. Поэтому: 1) проверки, которые могут не пройти, — до записи; 2) запись карточки;
     * 3) запись зависимых данных (вариант, цена, остаток, индекс) в транзакции; при сбое на шаге 3 новая карточка убирается.
     */
    public function save($data): bool
    {
        $db = $this->getDatabase();
        $shop = $this->shop();
        $userId = (int) Factory::getApplication()->getIdentity()->id;
        $isNew = empty($data['id']);

        try {
            $shop->variants()->assertSkuAvailable((int) ($data['id'] ?? 0), trim((string) ($data['sku'] ?? '')) ?: null);
        } catch (\RuntimeException $e) {
            $this->setError(Text::_($e->getMessage()));

            return false;
        }

        if (!parent::save($data)) {
            return false;
        }

        $id = (int) $this->getState($this->getName() . '.id');
        $db->transactionStart();

        try {
            $variantId = $shop->variants()->saveDefault($id, array_intersect_key($data, array_flip(self::VARIANT_FIELDS)));
            $shop->stock()->setQty($variantId, (float) str_replace(',', '.', (string) ($data['stock_qty'] ?? 0)), 'manual', $userId);
            $shop->rebuilder()->rebuild($id);
            $shop->indexer()->setCategories(
                $id,
                (int) $data['catid'],
                (array) ($data['extra_catids'] ?? []),
                (int) ($data['brand_id'] ?? 0),
                (array) ($data['badge_ids'] ?? [])
            );

            $db->transactionCommit();
        } catch (\Throwable $e) {
            $db->transactionRollback();
            Log::add($e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(), Log::ERROR, 'com_dharmashop');

            if ($isNew) {
                $this->getTable()->delete($id);
                $shop->variants()->deleteForProduct($id);
                $shop->indexer()->deleteProduct($id);
            }

            $this->setError(Text::_($e->getMessage()) ?: $e->getMessage());

            return false;
        }

        return true;
    }

    public function delete(&$pks): bool
    {
        $ids = array_map('intval', (array) $pks);

        if (!parent::delete($pks)) {
            return false;
        }

        $db = $this->getDatabase();
        $shop = $this->shop();

        foreach ($ids as $id) {
            $exists = (int) $db->setQuery($db->createQuery()->select('COUNT(*)')->from('#__dharmashop_products')->where('id = ' . $id))->loadResult();

            if ($exists) {
                continue;
            }

            $shop->variants()->deleteForProduct($id);
            $shop->indexer()->deleteProduct($id);
            $db->setQuery('DELETE FROM #__dharmashop_search WHERE product_id = ' . $id)->execute();
            $db->setQuery('DELETE FROM #__dharmashop_product_axes WHERE product_id = ' . $id)->execute();
        }

        return true;
    }

    public function publish(&$pks, $value = 1): bool
    {
        if (!parent::publish($pks, $value)) {
            return false;
        }

        $this->shop()->indexer()->refreshProducts(array_map('intval', (array) $pks));

        return true;
    }

    /** Отметка «рекомендуемый». */
    public function featured(array $pks, int $value = 1): bool
    {
        $pks = array_filter(array_map('intval', $pks));

        if (!$pks) {
            return false;
        }

        $user = Factory::getApplication()->getIdentity();

        foreach ($pks as $i => $pk) {
            if (!$user->authorise('core.edit.state', 'com_dharmashop.product.' . $pk)) {
                unset($pks[$i]);
                Factory::getApplication()->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_EDITSTATE_NOT_PERMITTED'), 'warning');
            }
        }

        if (!$pks) {
            return false;
        }

        $db = $this->getDatabase();
        $db->setQuery('UPDATE #__dharmashop_products SET featured = ' . (int) $value . ' WHERE id IN (' . implode(',', $pks) . ')')->execute();
        $this->shop()->indexer()->refreshProducts($pks);

        return true;
    }

    // ------------------------------------------------------------------ пакетная обработка

    /**
     * Применяет изменение таксономии к каждому выбранному товару, у которого пользователю разрешена правка.
     *
     * @param   int[]     $pks
     * @param   callable  $change  (int $id, object $row, array $taxonomy): ?array новая таксономия или null — пропустить
     */
    private function batchTaxonomy(array $pks, callable $change): bool
    {
        $db = $this->getDatabase();
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        $indexer = $this->shop()->indexer();

        foreach (array_map('intval', $pks) as $id) {
            $row = $db->setQuery($db->createQuery()->select('id, title, catid, created_by')->from('#__dharmashop_products')->where('id = ' . $id))->loadObject();

            if (!$row) {
                continue;
            }

            $canEdit = $user->authorise('core.edit', 'com_dharmashop.product.' . $id)
                || ($user->authorise('core.edit.own', 'com_dharmashop.product.' . $id) && (int) $row->created_by === (int) $user->id);

            if (!$canEdit) {
                $app->enqueueMessage(Text::sprintf('COM_DHARMASHOP_BATCH_SKIP_NO_ACCESS', $row->title), 'warning');
                continue;
            }

            $taxonomy = $indexer->getTaxonomy($id) + ['main' => (int) $row->catid];
            $taxonomy['main'] = (int) $row->catid;

            try {
                $new = $change($id, $row, $taxonomy);
            } catch (\RuntimeException $e) {
                $app->enqueueMessage(Text::sprintf('COM_DHARMASHOP_BATCH_SKIP', $row->title, Text::_($e->getMessage())), 'warning');
                continue;
            }

            if ($new === null) {
                continue;
            }

            $indexer->setCategories($id, (int) $new['main'], $new['extra'], (int) $new['brand'], $new['badges']);
        }

        return true;
    }

    /** Тип записи-категории по номеру или пустая строка. */
    private function categoryExtension(int $id): string
    {
        $db = $this->getDatabase();

        return (string) $db->setQuery($db->createQuery()->select('extension')->from('#__categories')->where('id = ' . $id))->loadResult();
    }

    /**
     * Приводит данные окна к виду для AdminModel::batch: блоки с действием «Пропустить» и без значения отбрасываются
     * (если не осталось ничего, штатная логика сообщит, что информации для обработки недостаточно).
     */
    public function batch($commands, $pks, $contexts)
    {
        foreach (array_keys($this->batch_commands) as $block) {
            $cmd = (array) ($commands[$block] ?? []);
            $action = (string) ($cmd['action'] ?? 'skip');
            $value = $cmd['value'] ?? '';
            $empty = is_array($value) ? !array_filter($value) : trim((string) $value) === '';

            if ($action === 'skip' || ($action !== 'clear' && $empty)) {
                unset($commands[$block]);
                continue;
            }

            $commands[$block] = ['action' => $action, 'value' => $value];
        }

        return parent::batch($commands, $pks, $contexts);
    }

    /** Идентификаторы из значения блока (одно или несколько). @return int[] */
    private static function batchIds(mixed $value): array
    {
        return array_values(array_unique(array_filter(array_map('intval', (array) $value))));
    }

    /** Проверяет, что все записи имеют нужный тип; иначе ошибка и false. */
    private function assertCategoryType(array $ids, string $type, string $errorKey): bool
    {
        foreach ($ids as $id) {
            if ($this->categoryExtension($id) !== $type) {
                $this->setError(Text::_($errorKey));

                return false;
            }
        }

        return true;
    }

    /** Переносит товары в основную категорию (меняет адрес товара, права наследуются от новой категории). */
    protected function batchMoveCategory($value, $pks, $contexts): bool
    {
        $target = (int) (self::batchIds($value['value'])[0] ?? 0);

        if (!$this->assertCategoryType([$target], CategoryTypes::CATEGORY, 'COM_DHARMASHOP_BATCH_ERROR_CATEGORY')) {
            return false;
        }

        if (!Factory::getApplication()->getIdentity()->authorise('core.create', 'com_dharmashop.category.' . $target)) {
            $this->setError(Text::_('COM_DHARMASHOP_BATCH_ERROR_CREATE_DENIED'));

            return false;
        }

        return $this->batchTaxonomy((array) $pks, function (int $id, object $row, array $tax) use ($target): ?array {
            if ((int) $row->catid === $target) {
                return null;
            }

            $table = $this->getTable();
            $table->load($id);
            $table->catid = $target;

            if (!$table->store()) {
                throw new \RuntimeException($table->getError());
            }

            $tax['main'] = $target;
            $tax['extra'] = array_values(array_diff($tax['extra'], [$target]));

            return $tax;
        });
    }

    /** Дополнительные категории: добавить / убрать / заменить набор. */
    protected function batchExtraCategories($value, $pks, $contexts): bool
    {
        $ids = self::batchIds($value['value']);
        $action = (string) $value['action'];

        if (!$this->assertCategoryType($ids, CategoryTypes::CATEGORY, 'COM_DHARMASHOP_BATCH_ERROR_CATEGORY')) {
            return false;
        }

        return $this->batchTaxonomy((array) $pks, function (int $id, object $row, array $tax) use ($ids, $action): ?array {
            $current = $tax['extra'];
            $new = match ($action) {
                'add' => array_merge($current, $ids),
                'remove' => array_diff($current, $ids),
                'replace' => $ids,
                default => $current,
            };
            $new = array_values(array_unique(array_diff(array_map('intval', $new), [$tax['main']])));

            if ($new === array_values($current)) {
                return null;
            }

            $tax['extra'] = $new;

            return $tax;
        });
    }

    /** Бренд: задать или снять. */
    protected function batchBrand($value, $pks, $contexts): bool
    {
        $action = (string) $value['action'];
        $brand = $action === 'clear' ? 0 : (self::batchIds($value['value'])[0] ?? 0);

        if ($action === 'set' && !$this->assertCategoryType([$brand], CategoryTypes::BRAND, 'COM_DHARMASHOP_BATCH_ERROR_BRAND')) {
            return false;
        }

        return $this->batchTaxonomy((array) $pks, function (int $id, object $row, array $tax) use ($brand): ?array {
            if ((int) $tax['brand'] === $brand) {
                return null;
            }

            $tax['brand'] = $brand;

            return $tax;
        });
    }

    /** Бейджи: добавить / убрать / заменить набор. */
    protected function batchBadges($value, $pks, $contexts): bool
    {
        $ids = self::batchIds($value['value']);
        $action = (string) $value['action'];

        if (!$this->assertCategoryType($ids, CategoryTypes::BADGE, 'COM_DHARMASHOP_BATCH_ERROR_BADGE')) {
            return false;
        }

        return $this->batchTaxonomy((array) $pks, function (int $id, object $row, array $tax) use ($ids, $action): ?array {
            $current = $tax['badges'];
            $new = match ($action) {
                'add' => array_merge($current, $ids),
                'remove' => array_diff($current, $ids),
                'replace' => $ids,
                default => $current,
            };
            $new = array_values(array_unique(array_map('intval', $new)));

            if ($new === array_values($current)) {
                return null;
            }

            $tax['badges'] = $new;

            return $tax;
        });
    }
}
