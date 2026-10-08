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

use Dharma\Component\DharmaShop\Administrator\Image\ImageCleaner;
use Dharma\Component\DharmaShop\Administrator\Catalog\CategoryPositionRules;
use Dharma\Component\DharmaShop\Administrator\Catalog\SearchIndexer;
use Dharma\Component\DharmaShop\Administrator\Order\InheritHints;
use Dharma\Component\DharmaShop\Administrator\Pricing\Discount;
use Dharma\Component\DharmaShop\Administrator\Service\ShopServices;
use Dharma\Component\DharmaShop\Administrator\Support\CategoryTypes;
use Dharma\Component\DharmaShop\Administrator\Seo\SeoHints;
use Dharma\Component\DharmaShop\Administrator\Seo\SeoSettings;
use Joomla\CMS\Component\ComponentHelper;
use Dharma\Component\DharmaShop\Administrator\Fields\FieldValues;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use Joomla\Database\ParameterType;
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
    private const VARIANT_FIELDS = ['sku', 'weight', 'weight_unit', 'length', 'width', 'height', 'dimension_unit', 'price', 'discount', 'discount_start', 'discount_end'];

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

        if (empty($form)) { return false; }
        if (true) {
            (new FieldValues($this->getDatabase()))->addToForm($form, 'product', $loadData ? $this->loadFormData() : $data, $loadData);
        }
        return $form;
    }

    protected function preprocessForm(Form $form, $data, $group = 'content'): void
    {
        // Подсказки «Наследуется: …» у правил оформления заказа по основной категории товара.
        $catid = (int) (is_array($data) ? ($data['catid'] ?? 0) : ($data->catid ?? 0));
        $rules = $this->shop()->orderRules();
        $inherited = $rules->forCategory($catid);
        InheritHints::apply($form, $rules, $inherited, null, '');

        // Количество и «продавать без остатка» — при учёте остатков: свой выбор «Да» либо «Из категории/настроек», если там «Да».
        $tracking = (int) ($inherited['values']['stock_tracked'] ?? 0) === 1 ? 'stock_tracked!:0' : 'stock_tracked:1';
        $form->setFieldAttribute('stock_qty', 'showon', $tracking);
        $form->setFieldAttribute('backorder', 'showon', $tracking);
        SeoHints::apply($form, new SeoSettings(ComponentHelper::getParams('com_dharmashop')), 'product');

        parent::preprocessForm($form, $data, $group);
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
        $item->params = (new Registry($item->params ?? ''))->toArray();
        $item->extra_catids = [];
        $item->brand_id = 0;
        $item->badge_ids = [];
        $item->sku = $item->price = $item->discount = $item->discount_start = $item->discount_end = null;
        $item->stock_qty = 0;
        $item->gallery = [];
        $item->category_order = [];
        $item->price_result = '';
        $params = ComponentHelper::getParams('com_dharmashop');
        $item->weight = $item->length = $item->width = $item->height = null;
        $item->weight_unit = (string) $params->get('shipping_weight_unit', 'кг');
        $item->dimension_unit = (string) $params->get('shipping_dimension_unit', 'см');

        if (!empty($item->id)) {
            $shop = $this->shop();
            $taxonomy = $shop->indexer()->getTaxonomy((int) $item->id);
            $item->extra_catids = $taxonomy['extra'];
            $item->brand_id = $taxonomy['brand'];
            $item->badge_ids = $taxonomy['badges'];
            $item->gallery = $shop->gallery()->load((int) $item->id);

            foreach ($this->getDatabase()->setQuery(
                $this->getDatabase()->createQuery()
                    ->select('pc.catid, c.title, pc.sort_order')
                    ->from($this->getDatabase()->quoteName('#__dharmashop_product_categories', 'pc'))
                    ->join('INNER', $this->getDatabase()->quoteName('#__categories', 'c') . ' ON c.id = pc.catid')
                    ->where('pc.product_id = ' . (int) $item->id)
                    ->where('c.extension = ' . $this->getDatabase()->quote('com_dharmashop'))
                    ->order('c.lft, c.title')
            )->loadObjectList() as $category) {
                $item->category_order[(int) $category->catid] = [
                    'catid' => (int) $category->catid,
                    'title' => (string) $category->title,
                    'action' => 'manual',
                    'sort_order' => (int) $category->sort_order,
                ];
            }

            if ($variant = $shop->variants()->getDefault((int) $item->id)) {
                foreach (self::VARIANT_FIELDS as $field) {
                    $item->$field = $variant->$field;
                }

                // Скидка в форме — одной строкой: «500» или «10%».
                $item->discount = Discount::format($variant->discount, $variant->discount_percent);
                $item->price_result = $this->priceResult($variant);
                $item->stock_qty = $shop->stock()->getQty((int) $variant->id);
            }
        }

        return $item;
    }

    /** Итог для поля «Цена со скидкой»: «1 500 ₽ (−500 ₽)»; если скидка не действует по срокам — пояснение. */
    private function priceResult(object $variant): string
    {
        if ($variant->discount === null || (float) $variant->discount <= 0 || $variant->price === null) {
            return '';
        }

        $money = $this->shop()->money();

        if ($variant->price_final === null || (float) $variant->price_final >= (float) $variant->price) {
            return Text::sprintf('COM_DHARMASHOP_DISCOUNT_INACTIVE', $money->format($variant->price));
        }

        return $money->format($variant->price_final) . ' (−' . $money->format((float) $variant->price - (float) $variant->price_final) . ')';
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

        // Скидка и цена проверяются до записи карточки, чтобы ошибка не оставила «полусохранённый» товар.
        try {
            $price = trim((string) ($data['price'] ?? ''));
            Discount::assertValid(Discount::parse($data['discount'] ?? null), $price === '' ? null : (float) str_replace(',', '.', $price));
        } catch (\InvalidArgumentException $e) {
            $this->setError(Text::_($e->getMessage()));

            return false;
        }

        try {
            $shop->variants()->assertSkuAvailable((int) ($data['id'] ?? 0), trim((string) ($data['sku'] ?? '')) ?: null);
        } catch (\RuntimeException $e) {
            $this->setError(Text::_($e->getMessage()));

            return false;
        }

        $fieldValues = new FieldValues($db);
        try { $preparedFields = $fieldValues->prepare('product', $data); }
        catch (\Throwable $error) { $this->setError($error->getMessage()); return false; }
        unset($data['custom_fields'], $data['custom_fields_present']);

        if (!parent::save($data)) {
            return false;
        }

        $id = (int) $this->getState($this->getName() . '.id');
        $removedFiles = [];
        $db->transactionStart();

        try {
            // Итоговые учёт остатков и «продавать без остатка» (товар → категории → настройки) записываем в вариант:
            // от них зависит наличие в списках.
            $row = $db->setQuery($db->createQuery()->select('*')->from('#__dharmashop_products')->where('id = ' . $id))->loadAssoc() ?: [];
            $policy = $shop->orderRules()->forProduct($row);
            $params = ComponentHelper::getParams('com_dharmashop');
            $variantData = array_intersect_key($data, array_flip(self::VARIANT_FIELDS)) + [
                'stock_tracked' => $policy['stock_tracked'],
                'backorder' => $policy['backorder'],
                'weight_unit' => (string) $params->get('shipping_weight_unit', 'кг'),
                'dimension_unit' => (string) $params->get('shipping_dimension_unit', 'см'),
            ];
            $variantId = $shop->variants()->saveDefault($id, $variantData);
            $shop->stock()->setQty($variantId, (float) str_replace(',', '.', (string) ($data['stock_qty'] ?? 0)), 'manual', $userId);
            $shop->rebuilder()->rebuild($id);
            $shop->indexer()->setCategories(
                $id,
                (int) $data['catid'],
                (array) ($data['extra_catids'] ?? []),
                (int) ($data['brand_id'] ?? 0),
                $this->withSaleBadge($variantId, (array) ($data['badge_ids'] ?? []), $params),
                (string) $params->get('new_product_position', 'last'),
                (new CategoryPositionRules($db, (string) $params->get('new_product_position', 'last')))
                    ->forCategories(array_merge([(int) $data['catid']], array_map('intval', (array) ($data['extra_catids'] ?? []))))
            );
            $shop->indexer()->setCategoryOrder($id, (array) ($data['category_order'] ?? []));

            // Галерея: обложка товара — первое изображение. Ключа нет, если вкладка не выводилась (пакетная обработка, консоль).
            if (array_key_exists('gallery', $data)) {
                $removedFiles = $shop->gallery()->save($id, (array) $data['gallery']);
            }

            $fieldValues->save('product', $id, $preparedFields);
            (new SearchIndexer($db))->rebuild($id);
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

        // Убранные из галереи файлы удаляются с диска, если они наши и больше нигде не используются (правила — ImageCleaner).
        $this->deleteFiles($removedFiles);

        return true;
    }

    /**
     * Бейдж скидки по настройкам: ставится, если у варианта по умолчанию действующая скидка (итоговая цена ниже обычной), и снимается, если её нет.
     * Если переключатель выключен или бейдж не выбран/удалён, набор бейджей не меняется.
     *
     * @param   int[]  $badges
     *
     * @return  int[]
     */
    private function withSaleBadge(int $variantId, array $badges, Registry $params): array
    {
        $saleId = (int) $params->get('sale_badge_id', 0);

        if ((int) $params->get('auto_sale_badge', 1) !== 1 || $saleId <= 0) {
            return $badges;
        }

        $db = $this->getDatabase();
        $extension = CategoryTypes::BADGE;
        $exists = (int) $db->setQuery(
            $db->createQuery()->select('COUNT(*)')->from($db->quoteName('#__categories'))
                ->where('id = :id')->where('extension = :extension')->where('published = 1')
                ->bind(':id', $saleId, ParameterType::INTEGER)->bind(':extension', $extension)
        )->loadResult();

        if (!$exists) {
            return $badges;
        }

        $discounted = (int) $db->setQuery(
            $db->createQuery()->select('COUNT(*)')->from($db->quoteName('#__dharmashop_variant_prices'))
                ->where('variant_id = :variant')->where('price > 0')->where('price_final < price')
                ->bind(':variant', $variantId, ParameterType::INTEGER)
        )->loadResult() > 0;
        $badges = array_values(array_unique(array_map('intval', $badges)));
        $without = array_values(array_diff($badges, [$saleId]));

        return $discounted ? array_merge($without, [$saleId]) : $without;
    }

    /** @param string[] $paths */
    private function deleteFiles(array $paths): void
    {
        if (!$paths) {
            return;
        }

        $cleaner = new ImageCleaner($this->getDatabase(), JPATH_ROOT, ComponentHelper::getParams('com_dharmashop'));

        foreach (array_unique($paths) as $path) {
            $cleaner->deleteIfUnused($path);
        }
    }

    public function delete(&$pks): bool
    {
        $ids = array_map('intval', (array) $pks);
        $db = $this->getDatabase();
        $files = [];

        // SEO-картинка лежит в params, поэтому запоминаем её до удаления карточек.
        foreach ($ids as $id) {
            $json = $db->setQuery($db->createQuery()->select('params')->from('#__dharmashop_products')->where('id = ' . $id))->loadResult();
            $params = json_decode((string) $json, true) ?: [];

            if (!empty($params['meta_image'])) {
                $files[] = (string) $params['meta_image'];
            }
        }

        if (!parent::delete($pks)) {
            return false;
        }

        $shop = $this->shop();

        foreach ($ids as $id) {
            $exists = (int) $db->setQuery($db->createQuery()->select('COUNT(*)')->from('#__dharmashop_products')->where('id = ' . $id))->loadResult();

            if ($exists) {
                continue;
            }

            $files = array_merge($files, $shop->gallery()->deleteForProduct($id));
            (new FieldValues($db))->deleteEntity('product', $id);
            $shop->variants()->deleteForProduct($id);
            $shop->indexer()->deleteProduct($id);
            $db->setQuery('DELETE FROM #__dharmashop_search WHERE product_id = ' . $id)->execute();
            $db->setQuery('DELETE FROM #__dharmashop_product_axes WHERE product_id = ' . $id)->execute();
        }

        // Файлы удалённых товаров: только наши и только если на них больше никто не ссылается.
        $this->deleteFiles($files);

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

    /**
     * Пересохраняет товары без открытия формы: правила оформления заказа, цена и наличие из вариантов, привязки индекса.
     *
     * @param   int[]  $pks  номера товаров
     *
     * @return  int  сколько товаров обработано (без тех, на которые нет права правки)
     */
    public function resave(array $pks): int
    {
        $user = Factory::getApplication()->getIdentity();
        $shop = $this->shop();
        $ids = [];

        foreach (array_filter(array_map('intval', $pks)) as $pk) {
            if ($user->authorise('core.edit', 'com_dharmashop.product.' . $pk) || $user->authorise('core.edit.state', 'com_dharmashop.product.' . $pk)) {
                $ids[] = $pk;
            } else {
                Factory::getApplication()->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_EDIT_NOT_PERMITTED'), 'warning');
            }
        }

        foreach ($ids as $pk) {
            $shop->orderRules()->materializeRange($pk, $pk);
            $shop->rebuilder()->rebuild($pk);
            (new SearchIndexer($this->getDatabase()))->rebuild($pk);
        }

        if ($ids) {
            $shop->indexer()->rebuildProducts($ids);
        }

        return count($ids);
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

            $params = ComponentHelper::getParams('com_dharmashop');
            $position = (string) $params->get('new_product_position', 'last');
            $categoryPositions = (new CategoryPositionRules($db, $position))->forCategories(array_merge([(int) $new['main']], $new['extra']));
            $indexer->setCategories($id, (int) $new['main'], $new['extra'], (int) $new['brand'], $new['badges'], $position, $categoryPositions);
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
