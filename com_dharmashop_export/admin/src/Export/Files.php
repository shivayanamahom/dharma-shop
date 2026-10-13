<?php
/** Dharma Shop — экспорт каталога. Copyright (C) 2026 Dharma. GPL-2.0-or-later. */
declare(strict_types=1);

namespace Dharma\Component\DharmaShopExport\Administrator\Export;
\defined('_JEXEC') or die;
/** Рабочие части и исторические артефакты — PHP-контейнеры с немедленным отказом HTTP. */
final class Files {
 public const GUARD="<?php http_response_code(404); exit; __halt_compiler();\n";
 private string $root;
 public function __construct(private array $storage) {
  $folder=$storage['folder'];
  if(!preg_match('~^files/[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*$~D',$folder)) {throw new \InvalidArgumentException('COM_DHARMASHOP_EXPORT_INVALID_FOLDER');}
  $base=realpath(JPATH_ROOT);$path=$base;
  foreach(explode('/',$folder) as $segment) {
   $path.='/'.$segment;if(is_link($path)){throw new \RuntimeException('COM_DHARMASHOP_EXPORT_INVALID_FOLDER');}
   if(!is_dir($path)&&!mkdir($path,0755)){throw new \RuntimeException('COM_DHARMASHOP_EXPORT_WRITE_ERROR');}
  }
  $this->root=$path;
  $this->directory($path.'/private');
  if(!is_file($path.'/private/.htaccess')){file_put_contents($path.'/private/.htaccess',"Require all denied\n");}
  if(!is_file($path.'/.htaccess')){file_put_contents($path.'/.htaccess',"Options -Indexes\nAddType text/csv .csv\nAddType application/xml .yml\nAddCharset UTF-8 .csv .yml\n");}
 }
 private function directory(string $path):void {
  if(is_link($path)){throw new \RuntimeException('COM_DHARMASHOP_EXPORT_INVALID_FOLDER');}
  if(!is_dir($path)&&!mkdir($path,0755)){throw new \RuntimeException('COM_DHARMASHOP_EXPORT_WRITE_ERROR');}
  if(!is_file($path.'/index.html')){file_put_contents($path.'/index.html','');}
 }
 public function path(int $job,string $name):string {
  if($job<1 || !preg_match('/^[a-z0-9_-]+\.php$/D',$name)){throw new \InvalidArgumentException('COM_DHARMASHOP_EXPORT_INVALID');}
  $dir=$this->root.'/private/'.$job;$this->directory($dir);$path=$dir.'/'.$name;
  if(is_link($path)){throw new \RuntimeException('COM_DHARMASHOP_EXPORT_INVALID_FOLDER');}return $path;
 }
 public function write(int $job,string $name,callable $write):void {
  $path=$this->path($job,$name);$tmp=$this->path($job,'tmp-'.bin2hex(random_bytes(8)).'.php');$stream=fopen($tmp,'xb');
  if(!$stream){throw new \RuntimeException('COM_DHARMASHOP_EXPORT_WRITE_ERROR');}
  try{Cells::put($stream,self::GUARD);$write($stream);fflush($stream);}catch(\Throwable $e){fclose($stream);unlink($tmp);throw $e;}
  fclose($stream);if(!rename($tmp,$path)){@unlink($tmp);throw new \RuntimeException('COM_DHARMASHOP_EXPORT_WRITE_ERROR');}
 }
 public function stream(int $job,string $name) {
  $stream=fopen($this->path($job,$name),'rb');
  if(!$stream || fread($stream,strlen(self::GUARD))!==self::GUARD){if(is_resource($stream)){fclose($stream);}throw new \RuntimeException('COM_DHARMASHOP_EXPORT_NO_FILE');}
  return $stream;
 }
 public function assemble(int $job,int $segments,FormatInterface $writer,array $c):array {
  $source=tempnam(sys_get_temp_dir(),'dharma-export-');$dest=tempnam(sys_get_temp_dir(),'dharma-export-');
  if(!$source||!$dest){throw new \RuntimeException('COM_DHARMASHOP_EXPORT_WRITE_ERROR');}
  try{
   $stream=fopen($source,'wb');if(!$stream){throw new \RuntimeException('COM_DHARMASHOP_EXPORT_WRITE_ERROR');}
   try{
    $writer->head($stream,$c);
    for($i=0;$i<$segments;$i++){ $part=$this->stream($job,'part-'.$i.'.php');try{if(stream_copy_to_stream($part,$stream)===false){throw new \RuntimeException('COM_DHARMASHOP_EXPORT_WRITE_ERROR');}}finally{fclose($part);} }
    $writer->tail($stream,$c);
   }finally{fclose($stream);}
   $writer->seal($source,$dest,$c);
   $bytes=filesize($dest);$hash=hash_file('sha256',$dest);
   $this->write($job,'artifact.php',static function($out)use($dest){$in=fopen($dest,'rb');try{if(stream_copy_to_stream($in,$out)===false){throw new \RuntimeException('COM_DHARMASHOP_EXPORT_WRITE_ERROR');}}finally{fclose($in);}});
   if($c['settings']['visibility']==='public'){
    // Номер профиля исключает коллизии имён разных профилей. Ссылка постоянна для профиля.
    $path=$this->publicPath($c['profile_id'],$c['settings']['file_name'],$writer->extension());
    if(is_link($path)){throw new \RuntimeException('COM_DHARMASHOP_EXPORT_INVALID_FOLDER');}
    $temp=$path.'.'.bin2hex(random_bytes(8)).'.tmp';
    if(!copy($dest,$temp)||!rename($temp,$path)){@unlink($temp);throw new \RuntimeException('COM_DHARMASHOP_EXPORT_WRITE_ERROR');}
   }
   return ['artifact'=>'artifact.php','bytes'=>$bytes,'sha256'=>$hash];
  }finally{if(is_file($source)){unlink($source);}if(is_file($dest)){unlink($dest);}}
 }
 public function publicPath(int $profile,string $name,string $extension):string {
  if($profile<1||!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]{0,79}$/D',$name)||!preg_match('/^[a-z0-9]{1,8}$/D',$extension)){throw new \InvalidArgumentException('COM_DHARMASHOP_EXPORT_INVALID_FILENAME');}
  return $this->root.'/'.$name.'-'.$profile.'.'.$extension;
 }
 /** Постоянная ссылка публичного профиля по снимку запуска, без обращения к файловой системе. */
 public static function publicLink(array $snapshot):string {
  $p=$snapshot['profile'];
  return $p['params']['site_url'].'/'.$snapshot['storage']['folder'].'/'.$p['params']['file_name'].'-'.(int)$p['id'].'.'.$snapshot['extension'];
 }
 public function publicUrl(array $job):string {
  $s=$job['snapshot'];$p=$s['profile'];$filename=basename($this->publicPath($p['id'],$p['params']['file_name'], $s['extension']));
  return $p['params']['site_url'].'/'.$s['storage']['folder'].'/'.$filename;
 }
 /** Удаляет папку запуска со всеми файлами (части, результат, пустышку index.html); ссылок не касается. */
 public function purge(int $job):void {
  $dir=dirname($this->path($job,'artifact.php'));
  foreach(glob($dir.'/*')?:[] as $path){if(is_file($path)&&!is_link($path)){unlink($path);}}
  if(is_dir($dir)&&!is_link($dir)){@rmdir($dir);}
 }
 public function clean(int $job):void {
  $dir=dirname($this->path($job,'artifact.php'));
  foreach(glob($dir.'/*.php')?:[] as $path){if(!is_link($path)){unlink($path);}}
 }
}
