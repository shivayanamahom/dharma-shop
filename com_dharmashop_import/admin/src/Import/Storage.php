<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShopImport\Administrator\Import;
use Joomla\CMS\Component\ComponentHelper;
\defined('_JEXEC') or die;

/** Every persisted input is an HTTP-denying PHP container. Never include these files. */
final class Storage
{
    public const GUARD = "<?php http_response_code(404); exit; __halt_compiler();\n";
    public const MAX_BYTES = 67108864;
    public const MAX_NORMALIZED_BYTES = 268435456;
    private string $directory;
    private string $folder;

    public static function uploadMaxBytes(): int
    {
        $limits=[self::MAX_BYTES];
        foreach(['upload_max_filesize','post_max_size'] as $key){$setting=trim((string)ini_get($key));$bytes=(int)((float)$setting*match(strtolower(substr($setting,-1))){'g'=>1073741824,'m'=>1048576,'k'=>1024,default=>1});if($bytes>0){$limits[]=$bytes;}}
        return min($limits);
    }

    public function __construct(?string $folder = null)
    {
        $folder ??= (string) ComponentHelper::getParams('com_dharmashop')->get('import_folder', 'files/import');
        if (!preg_match('~^files/[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*$~D', $folder)) { throw new \RuntimeException('COM_DHARMASHOP_IMPORT_PATH'); }
        $this->folder = $folder;
        $this->directory = rtrim(JPATH_ROOT, '/');
        foreach (explode('/', $folder . '/private') as $part) {
            $this->directory .= '/' . $part;
            if (is_link($this->directory) || (!is_dir($this->directory) && !mkdir($this->directory, 0750) && !is_dir($this->directory))) { throw new \RuntimeException('COM_DHARMASHOP_IMPORT_PATH'); }
        }
        $guard=$this->directory.'/.htaccess';
        if(is_link($guard)){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_PATH');}
        if(!is_file($guard)&&file_put_contents($guard,"Require all denied\n",LOCK_EX)===false){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_WRITE');}
    }

    public function path(string $token): string
    {
        if (!preg_match('/^[a-f0-9]{48}$/D', $token)) { throw new \RuntimeException('COM_DHARMASHOP_IMPORT_SOURCE_MISSING'); }
        return $this->directory . '/' . $token . '.php';
    }

    public function put(string $source, int $maximum = self::MAX_BYTES): array
    {
        $maximum = max(1, min(self::MAX_NORMALIZED_BYTES, $maximum));
        if (!is_file($source) || is_link($source) || filesize($source) > $maximum || filesize($source) < 1) { throw new \RuntimeException('COM_DHARMASHOP_IMPORT_SIZE'); }
        $token = bin2hex(random_bytes(24)); $path = $this->path($token);
        $in = fopen($source, 'rb'); $out = fopen($path, 'xb');
        if (!$in || !$out) { if (is_resource($in)) { fclose($in); } if (is_resource($out)) { fclose($out); } throw new \RuntimeException('COM_DHARMASHOP_IMPORT_WRITE'); }
        try {
            if (fwrite($out, self::GUARD) !== strlen(self::GUARD)) { throw new \RuntimeException('COM_DHARMASHOP_IMPORT_WRITE'); }
            $bytes=stream_copy_to_stream($in,$out,$maximum+1);
            if($bytes===false||$bytes<1||$bytes>$maximum){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_SIZE');}
        } catch (\Throwable $e) { @unlink($path); throw $e; }
        finally { fclose($in); fclose($out); }
        $saved=$this->stream($token);$hash=hash_init('sha256');
        try{hash_update_stream($hash,$saved);}finally{fclose($saved);}
        return ['folder'=>$this->folder, 'token'=>$token, 'bytes'=>$bytes, 'sha256'=>hash_final($hash)];
    }

    public function stream(string $token)
    {
        $path = $this->path($token);
        if (is_link($path) || !is_file($path)) { throw new \RuntimeException('COM_DHARMASHOP_IMPORT_SOURCE_MISSING'); }
        $in = fopen($path, 'rb');
        if (!$in || fread($in, strlen(self::GUARD)) !== self::GUARD) { if (is_resource($in)) { fclose($in); } throw new \RuntimeException('COM_DHARMASHOP_IMPORT_SOURCE_MISSING'); }
        return $in;
    }

    public function temporary(string $token): string
    {
        $path = self::temp();$in=$out=null;
        try { $in=$this->stream($token);$out=fopen($path,'wb');if (!$out || stream_copy_to_stream($in, $out) === false) { throw new \RuntimeException('COM_DHARMASHOP_IMPORT_WRITE'); } }
        catch(\Throwable $e){@unlink($path);throw $e;}
        finally { if(is_resource($in)){fclose($in);}if (is_resource($out)) { fclose($out); } }
        return $path;
    }

    public static function temp(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'dharma-import-');
        if ($path === false) { throw new \RuntimeException('COM_DHARMASHOP_IMPORT_WRITE'); }
        chmod($path, 0600); return $path;
    }
}
