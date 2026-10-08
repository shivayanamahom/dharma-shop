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

use Dharma\Component\DharmaShop\Administrator\Support\Sql;
use Joomla\CMS\Image\Image;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/** Пошагово конвертирует ранее загруженные растровые файлы и обновляет все ссылки Dharma Shop. */
final class ImageWebpMigration
{
    private readonly int $quality;

    private const CHUNK = 10;

    public function __construct(private readonly DatabaseInterface $db, private readonly string $root)
    {
        $this->quality = ImageQuality::resolve();
    }

    /**
     * @param array{phase?: string, id?: int, converted?: int, failed?: int} $cursor
     * @return array{processed: int, converted: int, failed: int, cursor: array, done: bool}
     */
    public function run(array $cursor = [], int $budgetMs = 5000): array
    {
        $phases = ['products', 'categories', 'media', 'settings'];
        $phase = (string) ($cursor['phase'] ?? 'products');
        $phaseIndex = array_search($phase, $phases, true);

        if ($phaseIndex === false) {
            $phaseIndex = 0;
            $phase = $phases[0];
        }

        $id = max(0, (int) ($cursor['id'] ?? 0));
        $converted = max(0, (int) ($cursor['converted'] ?? 0));
        $failed = max(0, (int) ($cursor['failed'] ?? 0));
        $processed = 0;
        $start = hrtime(true);
        $cache = [];

        while ($phaseIndex < count($phases)) {
            $rows = $this->loadRows($phase, $id);

            if ($rows === []) {
                $phaseIndex++;

                if ($phaseIndex >= count($phases)) {
                    return compact('processed', 'converted', 'failed') + ['cursor' => [], 'done' => true];
                }

                $phase = $phases[$phaseIndex];
                $id = 0;

                continue;
            }

            foreach ($rows as $row) {
                $id = (int) $row['id'];
                [$rowConverted, $rowFailed] = $this->processRow($phase, $row, $cache);
                $converted += $rowConverted;
                $failed += $rowFailed;
                $processed++;

                if ($phase === 'settings') {
                    $phaseIndex++;

                    if ($phaseIndex >= count($phases)) {
                        return compact('processed', 'converted', 'failed') + ['cursor' => [], 'done' => true];
                    }

                    $phase = $phases[$phaseIndex];
                    $id = 0;
                }

                if ((hrtime(true) - $start) / 1e6 >= $budgetMs) {
                    return compact('processed', 'converted', 'failed') + [
                        'cursor' => ['phase' => $phase, 'id' => $id, 'converted' => $converted, 'failed' => $failed],
                        'done' => false,
                    ];
                }
            }
        }

        return compact('processed', 'converted', 'failed') + ['cursor' => [], 'done' => true];
    }

    /** @return array<int, array<string, mixed>> */
    private function loadRows(string $phase, int $id): array
    {
        $queries = [
            'products' => 'SELECT id, cover, params FROM #__dharmashop_products WHERE id > :id ORDER BY id',
            'categories' => 'SELECT id, params FROM #__categories WHERE extension = :extension AND id > :id ORDER BY id',
            'media' => 'SELECT id, path FROM #__dharmashop_field_media WHERE id > :id ORDER BY id',
            'settings' => 'SELECT extension_id AS id, params FROM #__extensions WHERE type = :type AND element = :element AND extension_id > :id ORDER BY extension_id',
        ];
        $sql = $queries[$phase] ?? throw new \InvalidArgumentException('Unknown WebP migration phase.');
        $query = Sql::query($this->db, $sql, [':id' => [$id, ParameterType::INTEGER]]);

        if ($phase === 'categories') {
            $extension = 'com_dharmashop';
            $query->bind(':extension', $extension, ParameterType::STRING);
        } elseif ($phase === 'settings') {
            $type = 'component';
            $element = 'com_dharmashop';
            $query->bind(':type', $type, ParameterType::STRING);
            $query->bind(':element', $element, ParameterType::STRING);
        }

        return $this->db->setQuery($query, 0, self::CHUNK)->loadAssocList() ?: [];
    }

    /** @param array<string, string|false> $cache @param array<string, mixed> $row @return array{int, int} */
    private function processRow(string $phase, array $row, array &$cache): array
    {
        $id = (int) $row['id'];
        $converted = 0;
        $failed = 0;

        if ($phase === 'products') {
            $cover = (string) ($row['cover'] ?? '');
            [$newCover, $changed, $error] = $this->migrateReference($cover, $cache);
            $converted += $changed ? 1 : 0;
            $failed += $error ? 1 : 0;
            $params = $this->decodeParams((string) ($row['params'] ?? ''));
            [$metaImage, $changed, $error] = $this->migrateReference((string) ($params['meta_image'] ?? ''), $cache);
            $converted += $changed ? 1 : 0;
            $failed += $error ? 1 : 0;
            $paramsChanged = $metaImage !== (string) ($params['meta_image'] ?? '');

            if ($paramsChanged) {
                $params['meta_image'] = $metaImage;
            }

            if ($newCover !== $cover || $paramsChanged) {
                $json = $this->encodeParams($params);
                Sql::exec($this->db, 'UPDATE #__dharmashop_products SET cover = :cover, params = :params WHERE id = :id', [
                    ':cover' => [$newCover, ParameterType::STRING],
                    ':params' => [$json, ParameterType::STRING],
                    ':id' => [$id, ParameterType::INTEGER],
                ]);
            }
        } elseif ($phase === 'categories') {
            $params = $this->decodeParams((string) ($row['params'] ?? ''));
            $changed = false;

            foreach (['image', 'icon', 'meta_image'] as $key) {
                [$newPath, $updated, $error] = $this->migrateReference((string) ($params[$key] ?? ''), $cache);
                $converted += $updated ? 1 : 0;
                $failed += $error ? 1 : 0;

                if ($newPath !== (string) ($params[$key] ?? '')) {
                    $params[$key] = $newPath;
                    $changed = true;
                }
            }

            if ($changed) {
                $json = $this->encodeParams($params);
                Sql::exec($this->db, 'UPDATE #__categories SET params = :params WHERE id = :id', [
                    ':params' => [$json, ParameterType::STRING],
                    ':id' => [$id, ParameterType::INTEGER],
                ]);
            }
        } elseif ($phase === 'media') {
            $path = (string) ($row['path'] ?? '');
            [$newPath, $changed, $error] = $this->migrateReference($path, $cache);
            $converted += $changed ? 1 : 0;
            $failed += $error ? 1 : 0;

            if ($newPath !== $path) {
                Sql::exec($this->db, 'UPDATE #__dharmashop_field_media SET path = :path WHERE id = :id', [
                    ':path' => [$newPath, ParameterType::STRING],
                    ':id' => [$id, ParameterType::INTEGER],
                ]);
            }
        } else {
            $params = $this->decodeParams((string) ($row['params'] ?? ''));
            $changed = false;
            [$defaultImage, $updated, $error] = $this->migrateReference((string) ($params['seo_default_image'] ?? ''), $cache);
            $converted += $updated ? 1 : 0;
            $failed += $error ? 1 : 0;

            if ($defaultImage !== (string) ($params['seo_default_image'] ?? '')) {
                $params['seo_default_image'] = $defaultImage;
                $changed = true;
            }

            foreach (['root_category', 'root_brand'] as $rootKey) {
                if (!isset($params[$rootKey]) || !is_array($params[$rootKey])) {
                    continue;
                }

                foreach (['image', 'meta_image'] as $key) {
                    [$newPath, $updated, $error] = $this->migrateReference((string) ($params[$rootKey][$key] ?? ''), $cache);
                    $converted += $updated ? 1 : 0;
                    $failed += $error ? 1 : 0;

                    if ($newPath !== (string) ($params[$rootKey][$key] ?? '')) {
                        $params[$rootKey][$key] = $newPath;
                        $changed = true;
                    }
                }
            }

            if ($changed) {
                $json = $this->encodeParams($params);
                Sql::exec($this->db, 'UPDATE #__extensions SET params = :params WHERE extension_id = :id', [
                    ':params' => [$json, ParameterType::STRING],
                    ':id' => [$id, ParameterType::INTEGER],
                ]);
            }
        }

        return [$converted, $failed];
    }

    /** @param array<string, string|false> $cache @return array{string, bool, bool} новый путь, обновлена ли ссылка, сбой обработки */
    private function migrateReference(string $storedPath, array &$cache): array
    {
        if ($storedPath === '') {
            return ['', false, false];
        }

        $valid = ImageUploader::filterPath($storedPath);

        if ($valid === '' || !in_array(strtolower(pathinfo((string) strstr($valid . '#', '#', true), PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'avif'], true)) {
            return [$storedPath, false, false];
        }

        if (array_key_exists($valid, $cache)) {
            $newPath = $cache[$valid];

            return [$newPath === false ? $storedPath : $newPath, $newPath !== false && $newPath !== $storedPath, $newPath === false];
        }

        $plainPath = (string) strstr($valid . '#', '#', true);
        $suffix = substr($valid, strlen($plainPath));
        $absolute = rtrim($this->root, '/') . '/' . $plainPath;
        $realRoot = realpath($this->root);
        $realFile = realpath($absolute);

        if ($realRoot === false || $realFile === false || !str_starts_with($realFile, rtrim($realRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) || !is_file($realFile)) {
            $cache[$valid] = false;

            return [$storedPath, false, true];
        }

        $mime = (string) ((new \finfo(FILEINFO_MIME_TYPE))->file($realFile) ?: '');
        $info = @getimagesize($realFile);

        if ($info === false || !in_array($mime, ['image/jpeg', 'image/png', 'image/avif'], true) || ($info['mime'] ?? '') !== $mime) {
            $cache[$valid] = false;

            return [$storedPath, false, true];
        }

        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            throw new \RuntimeException('COM_DHARMASHOP_UPLOAD_ERROR_WEBP');
        }

        $directory = dirname($realFile);
        $temporary = tempnam($directory, '.dharmashop-webp-');

        if ($temporary === false) {
            $cache[$valid] = false;

            return [$storedPath, false, true];
        }

        $image = null;

        try {
            $image = new Image($realFile);

            if (!$image->toFile($temporary, IMAGETYPE_WEBP, ['quality' => $this->quality])) {
                $cache[$valid] = false;

                return [$storedPath, false, true];
            }

            $outputInfo = @getimagesize($temporary);

            if ($outputInfo === false || ($outputInfo['mime'] ?? '') !== 'image/webp' || (int) $outputInfo[0] !== (int) $info[0] || (int) $outputInfo[1] !== (int) $info[1]) {
                $cache[$valid] = false;

                return [$storedPath, false, true];
            }

            $targetPath = $this->publishWebp($temporary, $realFile, $directory);

            if ($targetPath === false) {
                $cache[$valid] = false;

                return [$storedPath, false, true];
            }

            $relative = ltrim(substr($targetPath, strlen(rtrim($realRoot, DIRECTORY_SEPARATOR))), DIRECTORY_SEPARATOR);
            $newPath = $relative . $suffix;
            $cache[$valid] = $newPath;

            return [$newPath, $newPath !== $storedPath, false];
        } catch (\Throwable) {
            $cache[$valid] = false;

            return [$storedPath, false, true];
        } finally {
            $image?->destroy();

            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    /** Создаёт WebP рядом с исходником, не перезаписывая ни оригиналы, ни уже существующие файлы. */
    private function publishWebp(string $temporary, string $source, string $directory): string|false
    {
        $hash = hash_file('sha256', $temporary);
        $stem = pathinfo($source, PATHINFO_FILENAME);
        $sourceHash = substr(hash_file('sha256', $source) ?: '', 0, 10);
        $candidates = [$directory . '/' . $stem . '.webp'];

        for ($i = 0; $i < 20; $i++) {
            $candidates[] = $directory . '/' . $stem . '-' . $sourceHash . ($i === 0 ? '' : '-' . ($i + 1)) . '.webp';
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                if (hash_file('sha256', $candidate) === $hash) {
                    return $candidate;
                }

                continue;
            }

            $reservation = @fopen($candidate, 'x');

            if ($reservation === false) {
                continue;
            }

            fclose($reservation);

            if (@rename($temporary, $candidate)) {
                @chmod($candidate, 0644);

                return $candidate;
            }

            @unlink($candidate);

            return false;
        }

        return false;
    }

    /** @return array<string, mixed> */
    private function decodeParams(string $value): array
    {
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $params */
    private function encodeParams(array $params): string
    {
        return json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
