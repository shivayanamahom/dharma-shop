<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Table;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * Карточка товара (#__dharmashop_products). Права — по ресурсу com_dharmashop.product.<id> с родителем-категорией.
 */
class ProductTable extends Table
{
    public function __construct(DatabaseInterface $db, ?DispatcherInterface $dispatcher = null)
    {
        parent::__construct('#__dharmashop_products', 'id', $db, $dispatcher);

        $this->setColumnAlias('published', 'state');
    }

    protected function _getAssetName(): string
    {
        return 'com_dharmashop.product.' . (int) $this->id;
    }

    protected function _getAssetTitle(): string
    {
        return (string) $this->title;
    }

    protected function _getAssetParentId(?Table $table = null, $id = null): int
    {
        if ($this->catid) {
            $db = $this->getDatabase();
            $catid = (int) $this->catid;
            $query = $db->createQuery()
                ->select($db->quoteName('asset_id'))
                ->from($db->quoteName('#__categories'))
                ->where($db->quoteName('id') . ' = :catid')
                ->bind(':catid', $catid, ParameterType::INTEGER);

            if ($assetId = (int) $db->setQuery($query)->loadResult()) {
                return $assetId;
            }
        }

        return parent::_getAssetParentId($table, $id);
    }

    public function bind($array, $ignore = ''): bool
    {
        // Скрытая или отключённая вкладка плагина не должна стирать его параметры.
        if (isset($array['params']) && is_array($array['params'])) {
            $array['params'] = array_replace((new Registry($this->params ?? ''))->toArray(), $array['params']);
        }

        foreach (['metadata', 'params'] as $key) {
            if (isset($array[$key]) && is_array($array[$key])) {
                $array[$key] = (string) new Registry($array[$key]);
            }
        }

        return parent::bind($array, $ignore);
    }

    public function check(): bool
    {
        try {
            parent::check();
        } catch (\Exception $e) {
            $this->setError($e->getMessage());

            return false;
        }

        if (trim((string) $this->title) === '') {
            $this->setError(Text::_('COM_DHARMASHOP_ERROR_TITLE_REQUIRED'));

            return false;
        }

        $this->alias = ApplicationHelper::stringURLSafe(trim((string) $this->alias) === '' ? (string) $this->title : (string) $this->alias);

        if (trim(str_replace('-', '', $this->alias)) === '') {
            $this->alias = Factory::getDate()->format('Y-m-d-H-i-s');
        }

        if (!$this->catid = (int) $this->catid) {
            $this->setError(Text::_('JLIB_DATABASE_ERROR_CATEGORY_REQUIRED'));

            return false;
        }

        $this->variants_mode = 1;
        foreach (['inherit_intro', 'inherit_full', 'inherit_images'] as $mode) {
            $this->$mode ??= 'own';
            if (!in_array($this->$mode, ['own', 'group', 'empty'], true)) {
                $this->setError(Text::_('COM_DHARMASHOP_GROUP_INHERIT_INVALID'));
                return false;
            }
        }

        // Текст для поиска: без тегов, лишние пробелы и пустые строки убираются; пусто — NULL.
        $this->search_text = trim(preg_replace('/[ \t]+/u', ' ', strip_tags((string) $this->search_text)) ?? '');
        $this->search_text = $this->search_text === '' ? null : $this->search_text;

        // Правила оформления заказа необязательны: пустое значение = «наследовать» (NULL).
        foreach (['unit', 'qty_min', 'qty_step', 'qty_max', 'stock_tracked', 'backorder', 'price_zero_free'] as $field) {
            if ($this->$field === '' || $this->$field === null) {
                $this->$field = null;
            }
        }

        foreach (['qty_min', 'qty_step'] as $field) {
            if ($this->$field !== null && (float) $this->$field <= 0) {
                $this->$field = null;
            }
        }

        if ($this->qty_max !== null && (float) $this->qty_max < 0) {
            $this->qty_max = null;
        }

        $this->metakey = trim(preg_replace('/\s*,\s*/', ', ', str_replace(["\n", "\r", '"', '<', '>'], '', (string) $this->metakey)), ', ');

        return true;
    }

    public function store($updateNulls = true): bool
    {
        $date = Factory::getDate()->toSql();
        $userId = (int) (Factory::getApplication()->getIdentity()->id ?? 0);

        if ($this->id) {
            $this->modified = $date;
            $this->modified_by = $userId;
        } else {
            $this->created = $this->created ?: $date;
            $this->created_by = $this->created_by ?: $userId;
            $this->modified = null;
        }

        // Алиас уникален в пределах основной категории (адрес товара строится по ней).
        $other = new self($this->getDatabase());

        if ($other->load(['alias' => $this->alias, 'catid' => $this->catid]) && (int) $other->id !== (int) $this->id) {
            $this->setError(Text::_('COM_DHARMASHOP_ERROR_ALIAS_EXISTS'));

            return false;
        }

        return parent::store($updateNulls);
    }
}
