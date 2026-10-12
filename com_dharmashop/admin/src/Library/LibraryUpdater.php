<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Library;

use Joomla\CMS\Factory;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\PluginHelper;

\defined('_JEXEC') or die;

/**
 * Обновление сторонних JavaScript-библиотек, которые лежат в плагинах пакета (Swiper, Fancybox).
 *
 * Порядок: номер последней версии — из GitHub Releases (в релизах нет собранных файлов), файлы — с jsDelivr (npm) той же версии с
 * проверкой SHA-256 по данным jsDelivr. Новая версия сначала скачивается и проверяется во временной папке; текущие файлы
 * копируются в `_backup` плагина (для отката), затем подменяются, а версия записывается в joomla.asset.json плагина
 * (она же сбрасывает кеш браузера). Разрешены только перечисленные адреса и файлы; ничего вне папки media плагина не пишется.
 */
final class LibraryUpdater
{
    /** Разрешённые узлы: версия — GitHub API, файлы — jsDelivr. */
    private const HOSTS = ['api.github.com', 'data.jsdelivr.com', 'cdn.jsdelivr.net'];

    /** Номер версии кешируется, чтобы не упираться в лимит GitHub API (60 запросов в час без ключа). */
    private const CACHE_SECONDS = 21600;

    private const MAX_FILE_BYTES = 2097152;

    /**
     * Библиотеки: ключ => параметры. files: путь внутри npm-пакета => путь внутри папки media плагина.
     * Новую библиотеку (плагин) добавляют записью сюда.
     */
    private const LIBRARIES = [
        'swiper' => [
            'title' => 'Swiper',
            'plugin' => ['system', 'dharmashopswiper'],
            'media' => 'plg_system_dharmashopswiper',
            'github' => 'nolimits4web/swiper',
            'npm' => 'swiper',
            'license' => 'MIT',
            'files' => [
                '/swiper-bundle.min.js' => 'js/swiper-bundle.min.js',
                '/swiper-bundle.min.css' => 'css/swiper-bundle.min.css',
                '/LICENSE' => 'LICENSE-swiper.txt',
            ],
        ],
        'fancybox' => [
            'title' => 'Fancybox',
            'plugin' => ['system', 'dharmashopfancybox'],
            'media' => 'plg_system_dharmashopfancybox',
            'github' => 'fancyapps/ui',
            'npm' => '@fancyapps/ui',
            'license' => 'Fancyapps UI License Agreement',
            'files' => [
                '/dist/fancybox/fancybox.umd.js' => 'js/fancybox.umd.js',
                '/dist/fancybox/fancybox.css' => 'css/fancybox.css',
                '/LICENSE.md' => 'LICENSE-fancybox.md',
            ],
        ],
    ];

    /** @return array<string, array<string, mixed>> */
    public function libraries(): array
    {
        return self::LIBRARIES;
    }

    public function exists(string $key): bool
    {
        return isset(self::LIBRARIES[$key]);
    }

    /**
     * Состояние библиотеки для страницы настроек.
     *
     * @return array<string, mixed>
     */
    public function status(string $key, bool $allowNetwork = true): array
    {
        $library = self::LIBRARIES[$key];
        $installed = $this->installedVersion($key);
        $latest = null;
        $error = null;

        if ($allowNetwork) {
            try {
                $latest = $this->latestVersion($key);
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        } else {
            $latest = $this->cachedLatest($key);
        }

        return [
            'key' => $key,
            'title' => $library['title'],
            'license' => $library['license'],
            'pluginEnabled' => PluginHelper::isEnabled($library['plugin'][0], $library['plugin'][1]),
            'installed' => $installed,
            'latest' => $latest,
            'error' => $error,
            'newer' => $installed !== null && $latest !== null && version_compare($latest, $installed, '>'),
            'major' => $installed !== null && $latest !== null && (int) $latest > (int) $installed,
            'backup' => $this->backupVersion($key),
        ];
    }

    /** Проверяет последние версии только по явному действию пользователя. */
    public function checkLatestVersions(): bool
    {
        $success = true;

        foreach (array_keys(self::LIBRARIES) as $key) {
            try {
                $this->latestVersion($key, true);
            } catch (\Throwable) {
                $success = false;
            }
        }

        return $success;
    }

    /** Установленная версия — из joomla.asset.json плагина. */
    public function installedVersion(string $key): ?string
    {
        $file = $this->mediaDir($key) . '/joomla.asset.json';

        if (!is_file($file)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) && isset($data['version']) && $this->validVersion((string) $data['version']) ? (string) $data['version'] : null;
    }

    /**
     * Обновляет библиотеку до последней стабильной версии. Мажорное обновление — только с явным подтверждением.
     *
     * @return string Установленная версия.
     */
    public function update(string $key, bool $allowMajor): string
    {
        $library = self::LIBRARIES[$key];
        $installed = $this->installedVersion($key);

        if ($installed === null) {
            throw new \RuntimeException('COM_DHARMASHOP_LIB_ERROR_NOT_INSTALLED');
        }

        $latest = $this->latestVersion($key, true);

        if (!version_compare($latest, $installed, '>')) {
            throw new \RuntimeException('COM_DHARMASHOP_LIB_ERROR_UP_TO_DATE');
        }

        if ((int) $latest > (int) $installed && !$allowMajor) {
            throw new \RuntimeException('COM_DHARMASHOP_LIB_ERROR_MAJOR');
        }

        $files = $this->download($key, $latest);
        $this->install($key, $installed, $latest, $files);
        Log::add('Библиотека ' . $library['title'] . ': ' . $installed . ' → ' . $latest, Log::INFO, 'com_dharmashop');

        return $latest;
    }

    /**
     * Возвращает файлы предыдущей версии из _backup.
     *
     * @return string Версия, к которой вернулись.
     */
    public function rollback(string $key): string
    {
        $backup = $this->backupDir($key);
        $meta = is_file($backup . '/meta.json') ? json_decode((string) file_get_contents($backup . '/meta.json'), true) : null;

        if (!is_array($meta) || !$this->validVersion((string) ($meta['version'] ?? ''))) {
            throw new \RuntimeException('COM_DHARMASHOP_LIB_ERROR_NO_BACKUP');
        }

        $version = (string) $meta['version'];
        $current = $this->installedVersion($key);
        $files = [];

        foreach (self::LIBRARIES[$key]['files'] as $target) {
            $source = $backup . '/' . $target;

            if (!is_file($source)) {
                throw new \RuntimeException('COM_DHARMASHOP_LIB_ERROR_NO_BACKUP');
            }

            $files[$target] = (string) file_get_contents($source);
        }

        // Откат — тоже подмена: текущие файлы становятся резервом, чтобы откат можно было отменить обновлением.
        $this->install($key, (string) $current, $version, $files);
        Log::add('Библиотека ' . self::LIBRARIES[$key]['title'] . ': откат ' . $current . ' → ' . $version, Log::INFO, 'com_dharmashop');

        return $version;
    }

    /** Версия резервной копии или null. */
    public function backupVersion(string $key): ?string
    {
        $file = $this->backupDir($key) . '/meta.json';
        $meta = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return is_array($meta) && $this->validVersion((string) ($meta['version'] ?? '')) ? (string) $meta['version'] : null;
    }

    private function mediaDir(string $key): string
    {
        return JPATH_ROOT . '/media/' . self::LIBRARIES[$key]['media'];
    }

    private function backupDir(string $key): string
    {
        return $this->mediaDir($key) . '/_backup';
    }

    private function validVersion(string $version): bool
    {
        return (bool) preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}$/D', $version);
    }

    private function cacheFile(string $key): string
    {
        return rtrim((string) Factory::getApplication()->get('cache_path', JPATH_CACHE), '/') . '/dharmashop_lib_' . $key . '.json';
    }

    private function cachedLatest(string $key): ?string
    {
        $file = $this->cacheFile($key);
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return is_array($data) && $this->validVersion((string) ($data['version'] ?? '')) ? (string) $data['version'] : null;
    }

    /** Последняя стабильная версия (GitHub Releases, предварительные не учитываются); кешируется на 6 часов. */
    private function latestVersion(string $key, bool $fresh = false): string
    {
        $file = $this->cacheFile($key);
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        if (!$fresh && is_array($data) && time() - (int) ($data['time'] ?? 0) < self::CACHE_SECONDS && $this->validVersion((string) ($data['version'] ?? ''))) {
            return (string) $data['version'];
        }

        $release = json_decode($this->fetch('https://api.github.com/repos/' . self::LIBRARIES[$key]['github'] . '/releases/latest'), true);
        $version = ltrim((string) ($release['tag_name'] ?? ''), 'v');

        if (!$this->validVersion($version)) {
            throw new \RuntimeException('COM_DHARMASHOP_LIB_ERROR_VERSION');
        }

        @file_put_contents($file, json_encode(['version' => $version, 'time' => time()]));

        return $version;
    }

    /** Загрузка только с разрешённых узлов по HTTPS. */
    private function fetch(string $url): string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);

        if (!str_starts_with($url, 'https://') || !in_array($host, self::HOSTS, true)) {
            throw new \RuntimeException('COM_DHARMASHOP_LIB_ERROR_HOST');
        }

        $response = HttpFactory::getHttp(['userAgent' => 'DharmaShop/0.1 (library updater)'])->get($url, ['Accept' => '*/*'], 20);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('COM_DHARMASHOP_LIB_ERROR_HTTP');
        }

        $body = (string) $response->getBody();

        if (strlen($body) > self::MAX_FILE_BYTES) {
            throw new \RuntimeException('COM_DHARMASHOP_LIB_ERROR_SIZE');
        }

        return $body;
    }

    /**
     * Скачивает файлы версии и сверяет SHA-256 с данными jsDelivr.
     *
     * @return array<string, string> путь в папке плагина => содержимое
     */
    private function download(string $key, string $version): array
    {
        $library = self::LIBRARIES[$key];
        $package = $library['npm'] . '@' . $version;
        $listing = json_decode($this->fetch('https://data.jsdelivr.com/v1/package/npm/' . $package . '/flat'), true);
        $hashes = [];

        foreach ((array) ($listing['files'] ?? []) as $file) {
            $hashes[(string) ($file['name'] ?? '')] = (string) ($file['hash'] ?? '');
        }

        $result = [];

        foreach ($library['files'] as $source => $target) {
            if (empty($hashes[$source])) {
                throw new \RuntimeException('COM_DHARMASHOP_LIB_ERROR_FILE');
            }

            $body = $this->fetch('https://cdn.jsdelivr.net/npm/' . $package . $source);

            if ($body === '' || !hash_equals($hashes[$source], base64_encode(hash('sha256', $body, true)))) {
                throw new \RuntimeException('COM_DHARMASHOP_LIB_ERROR_HASH');
            }

            $result[$target] = $body;
        }

        return $result;
    }

    /**
     * Подмена файлов: резерв текущей версии → запись новых файлов → версия в joomla.asset.json. При сбое записи прежние файлы
     * возвращаются из резерва.
     *
     * @param array<string, string> $files
     */
    private function install(string $key, string $oldVersion, string $newVersion, array $files): void
    {
        $media = $this->mediaDir($key);
        $backup = $this->backupDir($key);

        if (!is_dir($media) || !is_writable($media)) {
            throw new \RuntimeException('COM_DHARMASHOP_LIB_ERROR_WRITE');
        }

        // 1. Резерв текущих файлов.
        if (!is_dir($backup) && !@mkdir($backup, 0755, true) && !is_dir($backup)) {
            throw new \RuntimeException('COM_DHARMASHOP_LIB_ERROR_WRITE');
        }

        $previous = [];

        foreach (array_keys($files) as $target) {
            $path = $media . '/' . $target;
            $previous[$target] = is_file($path) ? (string) file_get_contents($path) : null;

            if ($previous[$target] !== null) {
                $this->write($backup . '/' . $target, $previous[$target]);
            }
        }

        if ($oldVersion !== '') {
            $this->write($backup . '/meta.json', (string) json_encode(['version' => $oldVersion, 'time' => time()]));
        }

        // 2. Новые файлы; при ошибке — вернуть прежние.
        try {
            foreach ($files as $target => $content) {
                $this->write($media . '/' . $target, $content);
            }

            $this->setAssetVersion($media . '/joomla.asset.json', $newVersion);
        } catch (\Throwable $e) {
            foreach ($previous as $target => $content) {
                if ($content !== null) {
                    @file_put_contents($media . '/' . $target, $content);
                }
            }

            throw $e;
        }
    }

    private function write(string $path, string $content): void
    {
        $directory = dirname($path);

        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('COM_DHARMASHOP_LIB_ERROR_WRITE');
        }

        $temporary = $path . '.tmp';

        if (@file_put_contents($temporary, $content) === false || !@rename($temporary, $path)) {
            @unlink($temporary);

            throw new \RuntimeException('COM_DHARMASHOP_LIB_ERROR_WRITE');
        }
    }

    /** Номер версии библиотеки в joomla.asset.json плагина (общий и у каждого ассета: он служит параметром сброса кеша браузера). */
    private function setAssetVersion(string $file, string $version): void
    {
        $data = json_decode((string) file_get_contents($file), true);

        if (!is_array($data)) {
            throw new \RuntimeException('COM_DHARMASHOP_LIB_ERROR_WRITE');
        }

        $data['version'] = $version;

        foreach ($data['assets'] ?? [] as $index => $asset) {
            if (isset($asset['version'])) {
                $data['assets'][$index]['version'] = $version;
            }
        }

        $this->write($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    }
}
