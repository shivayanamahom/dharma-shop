<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Image;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * Безопасное удаление файлов изображений при удалении записей. Правила (чтобы не сломать чужое):
 *  - удаляются только файлы внутри images/dharmashop/ (то, что загрузили через наш загрузчик); выбранные с сервера
 *    из других папок (например images/sampledata) не трогаются никогда;
 *  - файл не удаляется, если на него ссылается другая запись магазина (категория, бренд, бейдж, фото товара, обложка).
 */
final class ImageCleaner
{
    private const ROOT = 'images/dharmashop/';

    public function __construct(private readonly DatabaseInterface $db, private readonly string $siteRoot)
    {
    }

    /** Путь внутри нашей папки без выхода за её пределы или null. */
    public static function normalize(string $path): ?string
    {
        $path = ltrim(trim((string) strstr($path . '#', '#', true)), '/');

        if (!str_starts_with($path, self::ROOT) || str_contains($path, '..') || str_contains($path, "\0")) {
            return null;
        }

        return $path;
    }

    /**
     * Удаляет файл, если он наш и больше нигде не используется.
     *
     * @return  bool  true — файл удалён
     */
    public function deleteIfUnused(string $path): bool
    {
        $path = self::normalize($path);

        if ($path === null || $this->isReferenced($path)) {
            return false;
        }

        $file = rtrim($this->siteRoot, '/') . '/' . $path;

        return is_file($file) && @unlink($file);
    }

    /** Есть ли ссылка на путь в записях магазина. */
    public function isReferenced(string $path): bool
    {
        $base = basename($path);
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $base) . '%';

        // Категории, бренды, бейджи: картинка и иконка лежат в params (JSON, слэши экранированы) — ищем по имени файла и сверяем точно.
        $query = $this->db->createQuery()
            ->select('params')->from('#__categories')
            ->where('extension LIKE ' . $this->db->quote('com_dharmashop%'))
            ->where('params LIKE :like')->bind(':like', $like);

        foreach ($this->db->setQuery($query)->loadColumn() as $json) {
            $params = json_decode((string) $json, true) ?: [];

            foreach (['image', 'icon'] as $key) {
                if (isset($params[$key]) && self::normalize((string) $params[$key]) === $path) {
                    return true;
                }
            }
        }

        $pattern = $path . '%';

        foreach (['#__dharmashop_field_media' => 'path', '#__dharmashop_products' => 'cover'] as $table => $column) {
            $count = (int) $this->db->setQuery(
                $this->db->createQuery()->select('COUNT(*)')->from($table)->where($this->db->quoteName($column) . ' LIKE :p')->bind(':p', $pattern)
            )->loadResult();

            if ($count > 0) {
                return true;
            }
        }

        return false;
    }
}
