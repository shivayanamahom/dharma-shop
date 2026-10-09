<?php
declare(strict_types=1);
namespace Dharma\Plugin\DharmaShopField\Basic\Type;

\defined('_JEXEC') or die;

/** Parses public links into a small allow-listed provider contract; never accepts arbitrary iframe URLs. */
final class VideoProvider
{
    public static function parse(string $url, string $expected = ''): array
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 1000) { throw new \InvalidArgumentException('PLG_DHARMASHOPFIELD_BASIC_VIDEO_URL_INVALID'); }
        $parts = parse_url(str_contains($url, '://') ? $url : 'https://' . $url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = trim((string) ($parts['path'] ?? ''), '/');
        parse_str((string) ($parts['query'] ?? ''), $query);
        $provider = '';
        $id = '';
        $embed = '';
        $canonical = '';
        $posterKey = '';

        if (in_array($host, ['youtu.be', 'www.youtube.com', 'youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'], true)) {
            if ($host === 'youtu.be') { $id = explode('/', $path)[0] ?? ''; }
            elseif (str_starts_with($path, 'watch')) { $id = (string) ($query['v'] ?? ''); }
            elseif (preg_match('~^(?:embed|shorts|live)/([A-Za-z0-9_-]{11})~D', $path, $match)) { $id = $match[1]; }
            $provider = 'youtube';
            if (!preg_match('/^[A-Za-z0-9_-]{11}$/D', $id)) { throw new \InvalidArgumentException('PLG_DHARMASHOPFIELD_BASIC_VIDEO_URL_INVALID'); }
            $canonical = 'https://www.youtube.com/watch?v=' . $id;
            $embed = 'https://www.youtube-nocookie.com/embed/' . $id;
        } elseif (in_array($host, ['rutube.ru', 'www.rutube.ru', 'rutube.com', 'www.rutube.com'], true)) {
            if (preg_match('~^(?:video|play/embed|shorts)/([a-f0-9]{32})~iD', $path, $match)) { $id = strtolower($match[1]); }
            $provider = 'rutube';
            if ($id === '') { throw new \InvalidArgumentException('PLG_DHARMASHOPFIELD_BASIC_VIDEO_URL_INVALID'); }
            $token = (string) ($query['p'] ?? '');
            if ($token !== '' && !preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $token)) { throw new \InvalidArgumentException('PLG_DHARMASHOPFIELD_BASIC_VIDEO_URL_INVALID'); }
            $posterKey = $token;
            $canonical = 'https://rutube.ru/video/' . $id . ($token !== '' ? '/?p=' . rawurlencode($token) : '/');
            $embed = 'https://rutube.ru/play/embed/' . $id . '/' . ($token !== '' ? '?p=' . rawurlencode($token) : '');
        } elseif (in_array($host, ['vk.com', 'www.vk.com', 'm.vk.com', 'vkvideo.ru', 'www.vkvideo.ru'], true)) {
            if (preg_match('~^video(-?[0-9]+)_([0-9]+)~D', $path, $match)) { $id = $match[1] . '_' . $match[2]; }
            if (preg_match('~^video_ext\.php$~D', $path) && isset($query['oid'], $query['id'])) { $id = (string) $query['oid'] . '_' . (string) $query['id']; }
            $provider = 'vk';
            if (!preg_match('/^-?[0-9]{1,20}_[0-9]{1,20}$/D', $id)) { throw new \InvalidArgumentException('PLG_DHARMASHOPFIELD_BASIC_VIDEO_URL_INVALID'); }
            [$owner, $video] = explode('_', $id, 2);
            $hash = (string) ($query['hash'] ?? $query['access_key'] ?? '');
            if ($hash !== '' && !preg_match('/^[A-Za-z0-9_-]{1,160}$/D', $hash)) { throw new \InvalidArgumentException('PLG_DHARMASHOPFIELD_BASIC_VIDEO_URL_INVALID'); }
            $posterKey = $hash;
            $canonical = 'https://vk.com/video' . $owner . '_' . $video . ($hash !== '' ? '?hash=' . rawurlencode($hash) : '');
            $embed = 'https://vk.com/video_ext.php?oid=' . rawurlencode($owner) . '&id=' . rawurlencode($video) . ($hash !== '' ? '&hash=' . rawurlencode($hash) : '');
        } else {
            throw new \InvalidArgumentException('PLG_DHARMASHOPFIELD_BASIC_VIDEO_URL_INVALID');
        }
        if ($expected !== '' && $provider !== $expected) { throw new \InvalidArgumentException('PLG_DHARMASHOPFIELD_BASIC_VIDEO_PROVIDER_INVALID'); }
        return ['provider'=>$provider, 'id'=>$id, 'url'=>$canonical, 'embed'=>$embed, 'token'=>$posterKey];
    }
}
