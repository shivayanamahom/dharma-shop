<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Table;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\DispatcherInterface;

\defined('_JEXEC') or die;

/** A group has no asset_id and never owns its members' accounting records. */
class ProductgroupTable extends Table
{
    protected $_supportNullValue = true;

    public function __construct(DatabaseInterface $db, ?DispatcherInterface $dispatcher = null)
    {
        parent::__construct('#__dharmashop_product_groups', 'id', $db, $dispatcher);
        $this->setColumnAlias('published', 'state');
    }

    public function check(): bool
    {
        $this->title = trim(strip_tags((string) $this->title));
        $this->alias = ApplicationHelper::stringURLSafe(trim((string) $this->alias) ?: $this->title);
        // Legacy non-null column; public listing mode belongs to component settings.
        $this->display_mode ??= 'single';
        $this->state ??= 1;
        $this->copy_fields = (int) ($this->copy_fields ?? 1) === 1 ? 1 : 0;
        $this->catid = (int) $this->catid;
        $this->brand_id = (int) $this->brand_id ?: null;
        if ($this->catid < 1) {
            $this->setError(Text::_('JLIB_DATABASE_ERROR_CATEGORY_REQUIRED'));
            return false;
        }
        $this->title_template = trim(strip_tags((string) ($this->title_template ?? ''))) ?: \Dharma\Component\DharmaShop\Administrator\Catalog\GroupGenerator::DEFAULT_TEMPLATE;
        if (mb_strlen($this->title_template) > 1000) { $this->setError(Text::_('COM_DHARMASHOP_GENERATOR_TEMPLATE_INVALID')); return false; }
        $this->revision = max(1, (int) $this->revision);
        $this->in_stock = (int) $this->in_stock;
        $this->members_count = (int) $this->members_count;
        if (!$this->title || !$this->alias || mb_strlen($this->title) > 255 || mb_strlen($this->alias) > 190
            || !in_array((int) $this->state, [-2, 0, 1, 2], true)) {
            $this->setError(Text::_('COM_DHARMASHOP_GROUP_INVALID'));
            return false;
        }
        $db = $this->getDatabase();
        // Like Joomla content items, keep the generated slug and add a numeric suffix on collision.
        $baseAlias = $this->alias;
        $id = (int) $this->id;
        $suffix = 2;
        do {
            $alias = (string) $this->alias;
            $query = $db->createQuery()->select($db->quoteName('id'))
                ->from($db->quoteName('#__dharmashop_product_groups'))
                ->where($db->quoteName('alias') . ' = :alias')
                ->where($db->quoteName('id') . ' <> :id')
                ->bind(':alias', $alias)
                ->bind(':id', $id);
            $exists = $db->setQuery($query, 0, 1)->loadResult();
            if (!$exists) {
                break;
            }

            $suffixText = '-' . $suffix++;
            $this->alias = mb_substr($baseAlias, 0, 190 - mb_strlen($suffixText)) . $suffixText;
        } while (true);

        if (!$this->alias || mb_strlen($this->alias) > 190) {
            $this->setError(Text::_('COM_DHARMASHOP_GROUP_INVALID'));
            return false;
        }
        return parent::check();
    }

    public function delete($pk = null): bool
    {
        $id = (int) ($pk ?? $this->id);
        $db = $this->getDatabase();
        $db->transactionStart();
        try {
            $db->setQuery('SELECT id FROM #__dharmashop_product_groups WHERE id=' . $id . ' FOR UPDATE')->loadResult();
            if ($db->setQuery('SELECT id FROM #__dharmashop_products WHERE group_id=' . $id, 0, 1)->loadResult()) {
                $db->transactionRollback();
                $this->setError(Text::_('COM_DHARMASHOP_GROUP_HAS_MEMBERS'));
                return false;
            }
            if (!parent::delete($id)) { $db->transactionRollback(); return false; }
            (new \Dharma\Component\DharmaShop\Administrator\Catalog\GroupIndex($db))->delete([$id]);
            foreach (['group_axes', 'group_media', 'group_categories', 'group_field_values', 'group_field_texts', 'group_field_media'] as $suffix) {
                $db->setQuery('DELETE FROM #__dharmashop_' . $suffix . ' WHERE group_id=' . $id)->execute();
            }
            $db->transactionCommit();
            return true;
        } catch (\Throwable $error) {
            $db->transactionRollback();
            throw $error;
        }
    }
}
