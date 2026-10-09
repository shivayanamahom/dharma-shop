<?php
declare(strict_types=1);
namespace Dharma\Plugin\DharmaShopField\Basic\Type;

use Dharma\Component\DharmaShop\Administrator\Image\ImageUploader;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** Small, bounded metadata fetcher. Provider responses are data only; iframe HTML is never consumed. */
final class VideoMetadata
{
    public static function resolve(string $provider, string $url, array $settings, string $youtubeKey = ''): array
    {
        $video = VideoProvider::parse($url, $provider);
        $result = $video + ['title'=>'', 'poster'=>''];
        if ((int) ($settings['video_fetch_cover'] ?? 1) !== 1) { return $result; }
        try {
            [$metadata, $posterHosts] = match ($provider) {
                'youtube' => self::youtube($video, $youtubeKey),
                'rutube' => self::rutube($video),
                'vk' => self::vk($video),
                default => [[], []],
            };
            $result['title'] = mb_substr(trim(strip_tags((string) ($metadata['title'] ?? ''))), 0, 255);
            $posterUrl = (string) ($metadata['thumbnail_url'] ?? '');
            if ($posterUrl !== '' && $posterHosts) {
                $image = self::request($posterUrl, 10 * 1048576, $posterHosts);
                $mime = strtolower(trim(explode(';', (string) ($image['content_type'] ?? ''))[0]));
                $extension = match ($mime) { 'image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp', default=>'' };
                if ($extension !== '') {
                    $uploader = new ImageUploader(ComponentHelper::getParams('com_media'), JPATH_ROOT, ComponentHelper::getParams('com_dharmashop'));
                    $stored = $uploader->storeGenerated($image['body'], 'video-' . substr(hash('sha256', $video['provider'] . ':' . $video['id']), 0, 24) . '.' . $extension,
                        'field-video-posters', (int) ($settings['field_id'] ?? 0) . '/' . (string) ($settings['video_folder'] ?? 'product'));
                    $result['poster'] = $stored['path'];
                }
            }
        } catch (\Throwable) {
            // Metadata is optional: keep a valid video link when a provider API is unavailable.
        }
        return $result;
    }

    /** Best-effort local-video poster extraction. It is optional when FFmpeg is unavailable. */
    public static function localPoster(string $relativePath, int $fieldId, string $folder): string
    {
        try { return self::createLocalPoster($relativePath, $fieldId, $folder); }
        catch (\Throwable) { return ''; }
    }

    private static function createLocalPoster(string $relativePath, int $fieldId, string $folder): string
    {
        if ($fieldId < 1 || !function_exists('proc_open')) { return ''; }
        $images = realpath(JPATH_ROOT . '/images/dharmashop/videos');
        $source = realpath(JPATH_ROOT . '/' . ltrim($relativePath, '/'));
        if (!$images || !$source || !str_starts_with($source, $images . DIRECTORY_SEPARATOR) || !is_file($source)) { return ''; }

        $binary = '';
        // PHP-FPM often has a shorter PATH than the shell. Include common
        // installation directories so an installed FFmpeg is also found on web requests.
        $directories = explode(PATH_SEPARATOR, (string) getenv('PATH'));
        if (PHP_OS_FAMILY !== 'Windows') {
            $directories = array_merge($directories, ['/usr/local/bin', '/opt/homebrew/bin', '/usr/bin', '/bin']);
        }
        foreach (array_unique($directories) as $directory) {
            if ($directory === '') { continue; }
            $candidate = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ffmpeg';
            if (is_file($candidate) && is_executable($candidate)) { $binary = $candidate; break; }
        }
        if ($binary === '') { return ''; }

        $pipes = [];
        $process = @proc_open([$binary, '-hide_banner', '-loglevel', 'error', '-nostdin', '-ss', '0.5', '-i', $source,
            '-frames:v', '1', '-vf', 'scale=640:360:force_original_aspect_ratio=decrease', '-f', 'image2pipe', '-vcodec', 'mjpeg', 'pipe:1'],
            [0=>['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'], 1=>['pipe', 'w'], 2=>['pipe', 'w']], $pipes);
        if (!is_resource($process)) { return ''; }

        $posterBytes = '';
        $errorBytes = '';
        $started = microtime(true);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        try {
            do {
                $posterBytes .= (string) stream_get_contents($pipes[1]);
                $errorBytes .= (string) stream_get_contents($pipes[2]);
                if (strlen($posterBytes) > 10 * 1048576 || microtime(true) - $started > 10) {
                    proc_terminate($process);
                    break;
                }
                $status = proc_get_status($process);
                if (!$status['running']) { break; }
                usleep(100000);
            } while (true);
            $posterBytes .= (string) stream_get_contents($pipes[1]);
        } finally {
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }

        $image = $posterBytes !== '' ? @getimagesizefromstring($posterBytes) : false;
        if (!$image || ($image['mime'] ?? '') !== 'image/jpeg' || (int) $image[0] < 1 || (int) $image[1] < 1) { return ''; }
        try {
            $name = 'local-' . substr(hash('sha256', $fieldId . ':' . $folder . ':' . hash_file('sha256', $source)), 0, 24) . '.jpg';
            $uploader = new ImageUploader(ComponentHelper::getParams('com_media'), JPATH_ROOT, ComponentHelper::getParams('com_dharmashop'));
            $stored = $uploader->storeGenerated($posterBytes, $name, 'field-video-posters', $fieldId . '/' . $folder);
            return (string) ($stored['path'] ?? '');
        } catch (\Throwable) {
            return '';
        }
    }

    private static function youtube(array $video, string $key): array
    {
        $hosts = ['i.ytimg.com','img.youtube.com'];
        $fallback = 'https://img.youtube.com/vi/' . rawurlencode($video['id']) . '/hqdefault.jpg';
        $metadata = ['thumbnail_url'=>$fallback];
        if (trim($key) !== '' && strlen($key) <= 256) {
            try {
                $url = 'https://www.googleapis.com/youtube/v3/videos?part=snippet&id=' . rawurlencode($video['id']) . '&key=' . rawurlencode($key);
                $data = self::json($url, ['www.googleapis.com']);
                $snippet = (array) ($data['items'][0]['snippet'] ?? []);
                $metadata['title'] = (string) ($snippet['title'] ?? '');
                $thumbs = (array) ($snippet['thumbnails'] ?? []);
                foreach (['maxres','standard','high','medium','default'] as $size) {
                    if (!empty($thumbs[$size]['url'])) { $metadata['thumbnail_url'] = $thumbs[$size]['url']; break; }
                }
                if ($metadata['title'] !== '') { return [$metadata, $hosts]; }
            } catch (\Throwable) {
                // Public oEmbed also works when the Data API key is invalid or its quota is exhausted.
            }
        }
        try {
            $url = 'https://www.youtube.com/oembed?' . http_build_query(['url'=>$video['url'], 'format'=>'json']);
            $data = self::json($url, ['www.youtube.com']);
            $metadata['title'] = (string) ($data['title'] ?? '');
            if (!empty($data['thumbnail_url']) && $metadata['thumbnail_url'] === $fallback) {
                $metadata['thumbnail_url'] = $data['thumbnail_url'];
            }
        } catch (\Throwable) {
            // A title lookup failure must not prevent the standard cover or valid video link.
        }
        return [$metadata, $hosts];
    }

    private static function rutube(array $video): array
    {
        $data = self::json('https://rutube.ru/api/play/options/' . rawurlencode($video['id']) . '/', ['rutube.ru','www.rutube.ru']);
        return [['title'=>$data['title'] ?? '', 'thumbnail_url'=>$data['thumbnail_url'] ?? ''], ['pic.rutube.ru','pic.rtbcdn.ru']];
    }

    private static function vk(array $video): array
    {
        $query = http_build_query(['url'=>$video['url'], 'maxwidth'=>1280, 'maxheight'=>720, 'v'=>'5.199']);
        $data = self::json('https://api.vk.com/method/video.getOembed?' . $query, ['api.vk.com']);
        $response = (array) ($data['response'] ?? []);
        // VK may return previews from its own userapi CDN or from the OK CDN.
        // Both are used for VK video thumbnails; keep the host allowlist narrow.
        return [['title'=>$response['title'] ?? '', 'thumbnail_url'=>$response['thumbnail_url'] ?? ''], ['*.userapi.com', '*.okcdn.ru']];
    }

    private static function json(string $url, array $hosts): array
    {
        $response = self::request($url, 1024 * 1024, $hosts);
        $data = json_decode($response['body'], true);
        if (!is_array($data)) { throw new \RuntimeException('Invalid video API response'); }
        return $data;
    }

    /** HTTPS only, allow-listed host, no redirects, strict time and body limits. */
    private static function request(string $url, int $limit, array $hosts): array
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $hostAllowed = in_array($host, $hosts, true)
            || (in_array('*.userapi.com', $hosts, true) && preg_match('/^[a-z0-9-]+\.userapi\.com$/D', $host) === 1)
            || (in_array('*.okcdn.ru', $hosts, true) && preg_match('/^[a-z0-9-]+\.okcdn\.ru$/D', $host) === 1);
        if (($parts['scheme'] ?? '') !== 'https' || !$hostAllowed
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            throw new \RuntimeException('Untrusted video API host');
        }
        if (!function_exists('curl_init')) { throw new \RuntimeException('cURL is unavailable'); }
        $curl = curl_init($url);
        $body = ''; $contentType = '';
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER=>false, CURLOPT_FOLLOWLOCATION=>false, CURLOPT_MAXREDIRS=>0,
            CURLOPT_CONNECTTIMEOUT=>4, CURLOPT_TIMEOUT=>8, CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_USERAGENT=>'DharmaShop/0.1 video metadata',
            CURLOPT_HEADERFUNCTION=>static function ($curl, string $header) use (&$contentType): int {
                if (stripos($header, 'content-type:') === 0) { $contentType = trim(substr($header, 13)); }
                return strlen($header);
            },
            CURLOPT_WRITEFUNCTION=>static function ($curl, string $chunk) use (&$body, $limit): int {
                if (strlen($body) + strlen($chunk) > $limit) { return 0; }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($ok === false || $status !== 200 || $body === '') { throw new \RuntimeException($error ?: 'Video API request failed'); }
        return ['body'=>$body, 'content_type'=>$contentType];
    }
}
