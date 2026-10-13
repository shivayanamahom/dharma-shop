<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Favorites;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\CMS\Factory;

\defined('_JEXEC') or die;

/** Server-side favorites lists: one list per Joomla user or opaque guest token. */
final class FavoritesService
{
    private bool $schemaReady = false;

    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    public function prepareSchema(): void
    {
        $this->ensureSchema();
    }

    public function find(string $token, int $userId): ?object
    {
        $this->ensureSchema();
        $userList = $userId > 0 ? $this->byUser($userId) : null;
        $guestList = $this->byToken($token);

        if ($userList && $guestList && (int) $userList->id !== (int) $guestList->id) {
            $this->merge((int) $guestList->id, (int) $userList->id);
            return $userList;
        }

        if ($userList) {
            if ($guestList && (int) $userList->id === (int) $guestList->id) {
                $this->db->setQuery('UPDATE #__dharmashop_favorites SET token = NULL WHERE id = ' . (int) $userList->id)->execute();
            }
            return $userList;
        }

        if ($guestList && $userId > 0) {
            $this->db->setQuery('UPDATE #__dharmashop_favorites SET user_id = ' . $userId . ', token = NULL, modified = NOW() WHERE id = ' . (int) $guestList->id)->execute();
            $guestList->user_id = $userId;
            $guestList->token = null;
        }

        return $guestList;
    }

    public function ensure(string $token, int $userId): object
    {
        if ($list = $this->find($token, $userId)) {
            return $list;
        }

        $token = $userId > 0 ? '' : $token;
        if ($userId === 0 && $token === '') {
            $token = bin2hex(random_bytes(32));
        }

        $tokenValue = $token !== '' ? $token : null;
        $userValue = $userId > 0 ? $userId : null;
        $insert = $this->db->createQuery()->insert($this->db->quoteName('#__dharmashop_favorites'))
            ->columns(['token', 'user_id', 'created', 'modified'])
            ->values(':token, :user, NOW(), NOW()')
            ->bind(':token', $tokenValue)
            ->bind(':user', $userValue, ParameterType::INTEGER);
        try {
            $this->db->setQuery($insert)->execute();
        } catch (\Throwable $exception) {
            // Another request may have created this unique owner row after find().
            $list = $userId > 0 ? $this->byUser($userId) : $this->byToken($token);
            if (!$list) {
                throw $exception;
            }
            return $list;
        }

        $list = $userId > 0 ? $this->byUser($userId) : $this->byToken($token);
        if (!$list) {
            throw new \RuntimeException('Could not create favorites list');
        }
        $list->newToken = $userId > 0 ? '' : $token;
        return $list;
    }

    public function toggle(object $list, int $productId): array
    {
        $listId = (int) $list->id;
        $levels = $this->viewLevels();
        $product = $this->db->createQuery()->select('p.id')->from($this->db->quoteName('#__dharmashop_products', 'p'))
            ->join('INNER', $this->db->quoteName('#__categories', 'c') . ' ON c.id = p.catid AND c.published = 1 AND c.access IN (' . $levels . ')')
            ->where('p.id = :id')->where('p.state = 1')->bind(':id', $productId, ParameterType::INTEGER);
        if (!$this->db->setQuery($product)->loadResult()) {
            return ['ok' => false, 'error' => 'COM_DHARMASHOP_FAVORITES_PRODUCT_UNAVAILABLE'];
        }

        $check = $this->db->createQuery()->select('id')->from($this->db->quoteName('#__dharmashop_favorite_items'))
            ->where('favorites_id = :list')->where('product_id = :product')
            ->bind(':list', $listId, ParameterType::INTEGER)->bind(':product', $productId, ParameterType::INTEGER);
        $id = (int) $this->db->setQuery($check)->loadResult();
        if ($id) {
            $this->db->setQuery('DELETE FROM #__dharmashop_favorite_items WHERE id = ' . $id . ' AND favorites_id = ' . $listId)->execute();
            $added = false;
        } else {
            $this->db->setQuery('INSERT IGNORE INTO #__dharmashop_favorite_items (favorites_id, product_id, created) VALUES ('
                . $listId . ', ' . $productId . ', NOW())')->execute();
            $added = true;
        }
        $this->touch((int) $list->id);
        return ['ok' => true, 'added' => $added, 'count' => $this->count((int) $list->id)];
    }

    public function remove(object $list, int $productId): void
    {
        $listId = (int) $list->id;
        $this->db->setQuery($this->db->createQuery()->delete($this->db->quoteName('#__dharmashop_favorite_items'))
            ->where('favorites_id = :list')->where('product_id = :product')
            ->bind(':list', $listId, ParameterType::INTEGER)->bind(':product', $productId, ParameterType::INTEGER))->execute();
        $this->touch($listId);
    }

    public function clear(object $list): void
    {
        $this->db->setQuery('DELETE FROM #__dharmashop_favorite_items WHERE favorites_id = ' . (int) $list->id)->execute();
        $this->touch((int) $list->id);
    }

    public function count(int $listId): int
    {
        $levels = $this->viewLevels();
        return (int) $this->db->setQuery($this->db->createQuery()->select('COUNT(*)')->from($this->db->quoteName('#__dharmashop_favorite_items', 'fi'))
            ->join('INNER', $this->db->quoteName('#__dharmashop_products', 'p') . ' ON p.id = fi.product_id AND p.state = 1')
            ->join('INNER', $this->db->quoteName('#__categories', 'c') . ' ON c.id = p.catid AND c.published = 1 AND c.access IN (' . $levels . ')')
            ->where('fi.favorites_id = :id')->bind(':id', $listId, ParameterType::INTEGER))->loadResult();
    }

    /** @return int[] */
    public function productIds(int $listId): array
    {
        $levels = $this->viewLevels();
        return array_map('intval', $this->db->setQuery($this->db->createQuery()->select('fi.product_id')->from($this->db->quoteName('#__dharmashop_favorite_items', 'fi'))
            ->join('INNER', $this->db->quoteName('#__dharmashop_products', 'p') . ' ON p.id = fi.product_id AND p.state = 1')
            ->join('INNER', $this->db->quoteName('#__categories', 'c') . ' ON c.id = p.catid AND c.published = 1 AND c.access IN (' . $levels . ')')
            ->where('fi.favorites_id = :id')->bind(':id', $listId, ParameterType::INTEGER))->loadColumn());
    }

    /** @return object[] */
    public function products(int $listId): array
    {
        $levels = $this->viewLevels();
        return $this->db->setQuery('SELECT p.id, p.title, p.alias, p.catid, p.cover, p.cover_alt, p.introtext, p.price_min, p.price_max, '
            . 'p.price_zero_free, p.in_stock, p.unit, p.qty_min, p.qty_step, p.params, c.title AS category_title, fi.created AS favorited_at '
            . 'FROM #__dharmashop_favorite_items fi JOIN #__dharmashop_products p ON p.id = fi.product_id '
            . 'JOIN #__categories c ON c.id = p.catid AND c.published = 1 AND c.access IN (' . $levels . ') WHERE fi.favorites_id = ' . (int) $listId . ' AND p.state = 1 '
            . 'ORDER BY fi.created DESC, fi.id DESC')->loadObjectList();
    }

    private function byUser(int $userId): ?object
    {
        return $this->db->setQuery($this->db->createQuery()->select('*')->from($this->db->quoteName('#__dharmashop_favorites'))
            ->where('user_id = :id')->bind(':id', $userId, ParameterType::INTEGER))->loadObject() ?: null;
    }

    private function byToken(string $token): ?object
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) { return null; }
        return $this->db->setQuery($this->db->createQuery()->select('*')->from($this->db->quoteName('#__dharmashop_favorites'))
            ->where('token = :token')->bind(':token', $token))->loadObject() ?: null;
    }

    private function merge(int $from, int $to): void
    {
        $this->db->setQuery('INSERT IGNORE INTO #__dharmashop_favorite_items (favorites_id, product_id, created) '
            . 'SELECT ' . $to . ', product_id, created FROM #__dharmashop_favorite_items WHERE favorites_id = ' . $from)->execute();
        $this->db->setQuery('DELETE FROM #__dharmashop_favorite_items WHERE favorites_id = ' . $from)->execute();
        $this->db->setQuery('DELETE FROM #__dharmashop_favorites WHERE id = ' . $from)->execute();
        $this->touch($to);
    }

    private function touch(int $id): void
    {
        $this->db->setQuery('UPDATE #__dharmashop_favorites SET modified = NOW() WHERE id = ' . $id)->execute();
    }

    private function viewLevels(): string
    {
        $identity = Factory::getApplication()->getIdentity();
        $levels = array_values(array_filter(array_map('intval', $identity->getAuthorisedViewLevels()), static fn (int $id): bool => $id > 0));
        return $levels ? implode(',', $levels) : '1';
    }

    private function ensureSchema(): void
    {
        if ($this->schemaReady) { return; }
        // Таблицы создаёт установка пакета (`install.mysql.utf8.sql`); DDL на каждом запросе витрины не нужен (и требует права CREATE). Создаём только
        // если таблиц нет — сайт, обновлённый поверх старой версии.
        try {
            $this->db->setQuery('SELECT 1 FROM #__dharmashop_favorite_items LIMIT 0')->execute();
            $this->db->setQuery('SELECT 1 FROM #__dharmashop_favorites LIMIT 0')->execute();
            $this->schemaReady = true;
            return;
        } catch (\Throwable $error) {
            // создаём ниже
        }
        $this->db->setQuery('CREATE TABLE IF NOT EXISTS `#__dharmashop_favorites` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `token` char(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
            `user_id` int unsigned NULL,
            `created` datetime NOT NULL,
            `modified` datetime NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `uq_token` (`token`), UNIQUE KEY `uq_user` (`user_id`), KEY `idx_modified` (`modified`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci')->execute();
        $this->db->setQuery('CREATE TABLE IF NOT EXISTS `#__dharmashop_favorite_items` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `favorites_id` int unsigned NOT NULL,
            `product_id` int unsigned NOT NULL,
            `created` datetime NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `uq_favorites_product` (`favorites_id`,`product_id`),
            KEY `idx_product` (`product_id`,`favorites_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci')->execute();
        $this->schemaReady = true;
    }
}
