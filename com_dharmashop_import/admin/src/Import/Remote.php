<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShopImport\Administrator\Import;
\defined('_JEXEC') or die;

/** Stream to disk. Every redirect is independently authorised and DNS is pinned per request. */
final class Remote
{
    public static function validateUrl(string $url, array $hosts, bool $http = false): array
    {
        $parts = parse_url($url); $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        $allowed = array_map(static fn($h)=>strtolower(rtrim(trim((string) $h), '.')), $hosts);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!$parts || !in_array($scheme, $http ? ['https','http'] : ['https'], true) || !in_array($host, $allowed, true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) || isset($parts['port']) || preg_match('/[\x00-\x20\x7f]/', $url)) { throw new \RuntimeException('COM_DHARMASHOP_IMPORT_REMOTE_DENIED'); }
        return [$host,$scheme];
    }

    public static function inspectUrl(string $url, array $hosts, bool $http = false): array
    {
        [$host,$scheme]=self::validateUrl($url,$hosts,$http);
        $bare = trim($host, '[]');
        $addresses = filter_var($bare, FILTER_VALIDATE_IP) ? [$bare] : [];
        if (!$addresses) {
            foreach (dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) { $addresses[] = $record['ip'] ?? $record['ipv6'] ?? ''; }
        }
        if (!$addresses) { throw new \RuntimeException('COM_DHARMASHOP_IMPORT_REMOTE_DENIED'); }
        foreach ($addresses as $ip) {
            $check = str_starts_with(strtolower($ip), '::ffff:') ? substr($ip, 7) : $ip;
            if (!filter_var($check, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
                || preg_match('/^(?:0\.|127\.|169\.254\.|100\.(?:6[4-9]|[7-9]\d|1[01]\d|12[0-7])\.|198\.(?:18|19)\.)/', $check)
                || (str_contains($check, ':') && !preg_match('/^[23][0-9a-f]{3}:/i', $check))) { throw new \RuntimeException('COM_DHARMASHOP_IMPORT_REMOTE_DENIED'); }
        }
        return [$host, $scheme, $addresses[0]];
    }

    public static function get(string $url, array $hosts, bool $http = false, int $limit = Storage::MAX_BYTES): array
    {
        if (!function_exists('curl_init')) { throw new \RuntimeException('COM_DHARMASHOP_IMPORT_CURL'); }
        $deadline = microtime(true) + 25;
        for ($hop = 0; $hop <= 3; $hop++) {
            $remaining = (int) floor($deadline - microtime(true));
            if ($remaining < 1) { throw new \RuntimeException('COM_DHARMASHOP_IMPORT_REMOTE_FAILED'); }
            [$host, $scheme, $ip] = self::inspectUrl($url, $hosts, $http);
            $path = Storage::temp(); $out = fopen($path, 'wb'); $headers = []; $bytes = 0; $curl = curl_init($url);
            curl_setopt_array($curl, [CURLOPT_FOLLOWLOCATION=>false, CURLOPT_PROXY=>'', CURLOPT_PROTOCOLS=>CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_CONNECTTIMEOUT=>min(8,$remaining), CURLOPT_TIMEOUT=>$remaining, CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2,
                CURLOPT_RESOLVE=>[$host . ':' . ($scheme === 'https' ? 443 : 80) . ':' . (str_contains($ip, ':') ? '['.$ip.']' : $ip)],
                CURLOPT_USERAGENT=>'DharmaShop/0.1 import', CURLOPT_HEADERFUNCTION=>static function($c, string $line) use (&$headers): int {
                    if (str_contains($line, ':')) { [$key,$value] = explode(':',$line,2); $headers[strtolower(trim($key))] = trim($value); } return strlen($line);
                }, CURLOPT_WRITEFUNCTION=>static function($c, string $chunk) use ($out,$limit,&$bytes): int {
                    $bytes += strlen($chunk); return $bytes <= $limit ? (int) fwrite($out, $chunk) : 0;
                }]);
            try { $ok = curl_exec($curl); $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE); }
            finally { fclose($out); curl_close($curl); }
            if ($ok === false || $bytes > $limit) { @unlink($path); throw new \RuntimeException('COM_DHARMASHOP_IMPORT_REMOTE_FAILED'); }
            if (in_array($status, [301,302,303,307,308], true) && isset($headers['location']) && $hop < 3) {
                @unlink($path); $next = $headers['location'];
                if (str_starts_with($next, '//')) { $next = $scheme . ':' . $next; }
                elseif (!preg_match('~^https?://~i', $next)) { $base = parse_url($url); $next = $scheme.'://'.$host.(str_starts_with($next,'/') ? $next : rtrim(dirname($base['path'] ?? '/'),'/').'/'.$next); }
                if ($scheme === 'https' && str_starts_with(strtolower($next),'http:') && !$http) { throw new \RuntimeException('COM_DHARMASHOP_IMPORT_REMOTE_DENIED'); }
                $url = $next; continue;
            }
            if ($status !== 200 || $bytes < 1) { @unlink($path); throw new \RuntimeException('COM_DHARMASHOP_IMPORT_REMOTE_FAILED'); }
            $name = rawurldecode(basename((string) parse_url($url, PHP_URL_PATH))) ?: 'download';
            $disposition = $headers['content-disposition'] ?? '';
            if (preg_match("/filename\*=UTF-8''([^;]+)/i", $disposition, $m)) { $name = rawurldecode(trim($m[1])); }
            elseif (preg_match('/filename="([^"\r\n]+)"/i', $disposition, $m)) { $name = $m[1]; }
            return ['path'=>$path, 'name'=>basename(str_replace('\\','/',$name)), 'bytes'=>$bytes, 'mime'=>(new \finfo(FILEINFO_MIME_TYPE))->file($path)];
        }
        throw new \RuntimeException('COM_DHARMASHOP_IMPORT_REMOTE_FAILED');
    }
}
