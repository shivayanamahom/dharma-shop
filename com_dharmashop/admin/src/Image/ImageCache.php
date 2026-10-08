<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Image;
use Joomla\CMS\Uri\Uri;
\defined('_JEXEC') or die;

/** Enumerates only our versioned derivative filenames; never follows symlinks. */
final class ImageCache
{
    private string $root;
    private array $listings = [];
    public function __construct(public readonly string $relative)
    {
        if (!preg_match('~^images/(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9_-]+$~D', $relative)) throw new \RuntimeException('COM_DHARMASHOP_IMAGE_COMMAND_PATH');
        $this->root = (string)realpath(JPATH_ROOT);
        $path = $this->root;
        foreach (explode('/', $relative) as $part) {
            $path .= '/'.$part;
            if (is_link($path) || (file_exists($path) && !is_dir($path))) throw new \RuntimeException('COM_DHARMASHOP_IMAGE_COMMAND_PATH');
        }
    }

    public function referencedPath(string $source): ?string
    {
        $url = parse_url(ImageUrl::absolute($source)); $site = parse_url(Uri::root());
        if (!$url || !$site || strtolower($url['host'] ?? '') !== strtolower($site['host'] ?? '') || ($url['port'] ?? null) !== ($site['port'] ?? null)) return null;
        $prefix = rtrim(rawurldecode($site['path'] ?? ''), '/').'/';
        $path = rawurldecode($url['path'] ?? '');
        if (!str_starts_with($path,$prefix) || str_contains($path,"\0")) return null;
        $real = realpath($this->root.'/'.substr($path,strlen($prefix)));
        if (!$real || !str_starts_with($real,$this->root.'/'.$this->relative.'/')) return null;
        return substr($real,strlen($this->root)+1);
    }

    /** Each call visits at most one directory entry, including unrecognised entries. */
    public function next(array &$cursor): string|false|null
    {
        if (!$cursor) $cursor = [['dir'=>'', 'after'=>'']];
        $top = count($cursor)-1; $dir = $cursor[$top]['dir'];
        $absolute = $this->root.'/'.$this->relative.($dir === '' ? '' : '/'.$dir);
        if (!$this->safe($absolute) || !is_dir($absolute)) $entries = [];
        else {
            if (!isset($this->listings[$dir])) {
                $entries = [];
                foreach (new \FilesystemIterator($absolute, \FilesystemIterator::SKIP_DOTS) as $entry) {
                    $entries[] = $entry->getFilename();
                    // Fail closed on a malformed/unsharded directory instead of exhausting PHP memory.
                    if (count($entries) > 20000) throw new \RuntimeException('COM_DHARMASHOP_IMAGE_COMMAND_BUCKET');
                }
                sort($entries,SORT_STRING); $this->listings[$dir] = $entries;
            }
            $entries = $this->listings[$dir];
        }
        $name = null;
        foreach ($entries as $entry) if (strcmp($entry,$cursor[$top]['after']) > 0) { $name = $entry; break; }
        if ($name === null) {
            array_pop($cursor); unset($this->listings[$dir]);
            return $cursor ? null : false;
        }
        $cursor[$top]['after'] = $name;
        $path = $absolute.'/'.$name;
        if (!$this->safe($path)) return null;
        $parts = $dir === '' ? [] : explode('/',$dir);
        $depth = count($parts);
        $directory = $depth === 0 ? in_array($name,['v1','v2'],true)
            : ($parts[0] === 'v1' && $depth === 1 ? isset(ImageContexts::SIZES[$name])
            : ($depth < (($parts[0] ?? '') === 'v1' ? 4 : 3) && preg_match('/^[a-f0-9]{2}$/D',$name)));
        if ($directory && is_dir($path)) { $cursor[] = ['dir'=>ltrim($dir.'/'.$name,'/'), 'after'=>'']; return null; }
        $leaf = $depth === (($parts[0] ?? '') === 'v1' ? 4 : 3);
        if ($leaf && is_file($path) && preg_match('/^(?:(?:auto|[0-9]{1,4})x(?:auto|[0-9]{1,4})-(?:contain|cover|stretch)-(?:q[0-9]{1,3}|lossless)-)?([a-f0-9]{32})-[a-f0-9]{24}\.(?:jpg|png|webp|avif)$/D',$name,$match)) {
            if ($parts[$depth-2] === substr($match[1],0,2) && $parts[$depth-1] === substr($match[1],2,2)) return $this->relative.'/'.$dir.'/'.$name;
        }
        return null;
    }

    public function size(string $path): int { return (int)@filesize($this->root.'/'.$path); }
    public function modified(string $path): int { return (int)@filemtime($this->root.'/'.$path); }

    /** Synchronise with the generator; lock files stay to avoid unlink/recreate inode races. */
    public function remove(string $relative, int $cutoff): bool
    {
        $file = $this->root.'/'.$relative;
        if (!$this->safe($file) || !is_file($file) || is_link($file.'.lock')) return false;
        $lock = @fopen($file.'.lock','c');
        if (!$lock) return false;
        try {
            if (!flock($lock,LOCK_EX | LOCK_NB)) return false;
            clearstatcache(true,$file);
            return $this->safe($file) && is_file($file) && (int)@filemtime($file) <= $cutoff && @unlink($file);
        } finally { flock($lock,LOCK_UN); fclose($lock); }
    }

    private function safe(string $path): bool
    {
        $relative = substr($path,strlen($this->root)+1); $part = $this->root;
        foreach (explode('/',$relative) as $segment) { $part .= '/'.$segment; if (is_link($part)) return false; }
        $real = realpath($path);
        return $real !== false && str_starts_with($real,$this->root.'/'.$this->relative.($path === $this->root.'/'.$this->relative ? '' : '/'));
    }
}
