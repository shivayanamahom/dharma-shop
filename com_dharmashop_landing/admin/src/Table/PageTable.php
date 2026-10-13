<?php

/**
 * Dharma Shop — посадочные страницы (C85).
 *
 * @package    com_dharmashop_landing
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShopLanding\Administrator\Table;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;

\defined('_JEXEC') or die;

/** Посадочная страница: заголовок, адрес, источник товаров, условия, тексты и SEO. */
class PageTable extends Table
{
    protected $_supportNullValue = true;

    public function __construct(DatabaseInterface $db, ?DispatcherInterface $dispatcher = null)
    {
        parent::__construct('#__dharmashop_landing_pages', 'id', $db, $dispatcher);
        $this->setColumnAlias('published', 'state');
    }

    public function check(): bool
    {
        $this->title = trim(strip_tags((string) $this->title));
        $this->alias = ApplicationHelper::stringURLSafe(trim((string) $this->alias) ?: $this->title);
        $this->state = (int) $this->state;
        $this->access = max(1, (int) $this->access);
        $this->ordering = (int) $this->ordering;
        $this->catid = max(0, (int) $this->catid);
        $this->source = (string) $this->source;
        $this->sort = (string) $this->sort;
        $this->product_view = (string) $this->product_view;
        $this->image = trim((string) $this->image);
        $this->image_alt = trim(strip_tags((string) $this->image_alt));
        $this->meta_title = trim(strip_tags((string) $this->meta_title));
        $this->meta_description = trim(strip_tags((string) $this->meta_description));
        $this->robots = (string) $this->robots;

        if ($this->title === '' || mb_strlen($this->title) > 255 || $this->alias === '' || mb_strlen($this->alias) > 190
            || !in_array($this->state, [0, 1], true) || !in_array($this->source, ['filter', 'manual'], true)
            || !in_array($this->sort, ['', 'order', 'price_asc', 'price_desc', 'new', 'title'], true)
            || !in_array($this->product_view, ['', 'grid', 'list', 'table'], true)
            || !in_array($this->robots, ['', 'index, follow', 'noindex, follow', 'index, nofollow', 'noindex, nofollow'], true)
            || mb_strlen($this->image) > 500 || mb_strlen($this->image_alt) > 255 || mb_strlen($this->meta_title) > 255 || mb_strlen($this->meta_description) > 500) {
            $this->setError(Text::_('COM_DHARMASHOP_LANDING_INVALID'));

            return false;
        }

        $db = $this->getDatabase();
        $id = (int) $this->id;
        $alias = (string) $this->alias;
        $query = $db->createQuery()->select('id')->from($db->quoteName('#__dharmashop_landing_pages'))->where('alias = :a AND id <> :id')
            ->bind(':a', $alias)->bind(':id', $id, ParameterType::INTEGER);

        if ($db->setQuery($query, 0, 1)->loadResult()) {
            $this->setError(Text::_('COM_DHARMASHOP_LANDING_ALIAS_EXISTS'));

            return false;
        }

        $now = gmdate('Y-m-d H:i:s');
        $userId = (int) \Joomla\CMS\Factory::getApplication()->getIdentity()->id;

        if ($id) {
            $this->modified = $now;
            $this->modified_by = $userId;
        } else {
            $this->created = $this->created ?: $now;
            $this->created_by = $this->created_by ?: $userId;

            if ((int) $this->ordering === 0) {
                $this->ordering = 1 + (int) $db->setQuery('SELECT COALESCE(MAX(ordering), 0) FROM ' . $db->quoteName('#__dharmashop_landing_pages'))->loadResult();
            }
        }

        return parent::check();
    }
}
