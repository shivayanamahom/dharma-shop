<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShopImport\Administrator\Import;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
\defined('_JEXEC') or die;

final class Workflow
{
    public function __construct(private DatabaseInterface $db)
    {
        Factory::getApplication()->getLanguage()->load('com_dharmashop',JPATH_ADMINISTRATOR);
        Factory::getApplication()->getLanguage()->load('com_dharmashop_import',JPATH_ADMINISTRATOR);
        Factory::getApplication()->getLanguage()->load('com_dharmashop_export',JPATH_ADMINISTRATOR); // подписи колонок нашего экспорта для автосопоставления
    }
    private function save(string $table, int $id, array $values): void {$object=(object)(['id'=>$id]+$values);$this->db->updateObject($table,$object,'id',true);}
    private function keyHash(string $key): string
    {
        // Match the same case/accent/trailing-space rules as external_ids, rather than PHP byte equality.
        $weight=$this->db->setQuery('SELECT HEX(WEIGHT_STRING(CONVERT('.$this->db->quote($key).' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS CHAR(100)))')->loadResult();
        return hash('sha256',(string)$weight);
    }
    public function profile(int $id): array
    {
        $p=$this->db->setQuery('SELECT id,title,state,entity,format,params FROM #__dharmashop_import_profiles WHERE id='.$id)->loadAssoc();
        if(!$p){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_NOT_FOUND');}$p['params']=json_decode($p['params'],true,64,JSON_THROW_ON_ERROR);return $p;
    }
    public function job(int $id): array
    {
        $job=$this->db->setQuery('SELECT id,profile_id,state,snapshot,`cursor`,line,total,processed,created_count,updated_count,unchanged_count,errors,created_by,approved_by,created,updated,finished,error,lease,lease_until FROM #__dharmashop_import_jobs WHERE id='.$id)->loadAssoc();
        if(!$job){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_NOT_FOUND');}$job['snapshot']=json_decode($job['snapshot'],true,64,JSON_THROW_ON_ERROR);return $job;
    }
    public function upload(array $upload): array
    {
        Access::require('dharmashop.import.check');
        if(($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file($upload['tmp_name']??'')){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_UPLOAD');}
        $name=basename(str_replace('\\','/',(string)$upload['name']));if(!in_array(strtolower(pathinfo($name,PATHINFO_EXTENSION)),['csv','xlsx'],true)){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_FORMAT_ERROR');}
        return $this->source($upload['tmp_name'],$name);
    }
    public function source(string $path, string $name, string $kind = 'source', int $maximum = Storage::MAX_BYTES): array
    {
        $s=(new Storage())->put($path,$maximum);$record=(object)(['job_id'=>null,'created_by'=>(int)Factory::getApplication()->getIdentity()->id,'kind'=>$kind,'state'=>'ready','name'=>mb_substr(basename($name),0,255),'created'=>gmdate('Y-m-d H:i:s')]+$s);
        $this->db->insertObject('#__dharmashop_import_files',$record,'id');return ['id'=>(int)$record->id,'path'=>$record->token,'filename'=>$record->name,'bytes'=>$record->bytes];
    }
    public function file(int $id, string $kind = 'source'): array
    {
        $file=$this->db->setQuery('SELECT id,created_by,kind,state,token,folder,name,bytes,sha256,mime FROM #__dharmashop_import_files WHERE id='.$id)->loadAssoc();
        if(!$file||$file['kind']!==$kind||((int)$file['created_by']!==(int)Factory::getApplication()->getIdentity()->id&&!Factory::getApplication()->getIdentity()->authorise('dharmashop.import.report','com_dharmashop_import'))){throw new \RuntimeException('JERROR_ALERTNOAUTHOR',403);}return $file;
    }
    public function sample(array $profile, array $source): array
    {
        if(($profile['format']??'csv')==='csv'){return (new CsvReader($profile['params']+['storage_folder'=>$source['folder']]))->sample($source['token']);}
        $path=(new Storage($source['folder']))->temporary($source['token']);
        try{return ReaderRegistry::get((string)$profile['format'])->sample($path,(string)($profile['params']['xlsx_sheet']??''),!empty($profile['params']['header']));}
        finally{@unlink($path);}
    }
    private function readerSource(array $profile, array $source): array
    {
        if(($profile['format']??'csv')==='csv'){return $source;}
        $path=(new Storage($source['folder']))->temporary($source['token']);$normalized=null;
        try{$normalized=ReaderRegistry::get((string)$profile['format'])->normalize($path,(string)($profile['params']['xlsx_sheet']??''));return $this->file((int)$this->source($normalized,'normalized.csv','normalized',Storage::MAX_NORMALIZED_BYTES)['id'],'normalized');}
        finally{@unlink($path);if($normalized){@unlink($normalized);}}
    }
    public function receive(array $profile): array
    {
        $p=$profile['params'];
        if($p['source_type']==='upload'){return $this->file((int)($p['source_id']??0));}
        if($p['source_type']==='url'){
            $url=(string)($p['source_url']??'');$host=(string)parse_url($url,PHP_URL_HOST);$result=Remote::get($url,[$host],!empty($p['allow_http']));
            try{$s=$this->source($result['path'],$result['name']);return $this->file($s['id']);}finally{unlink($result['path']);}
        }
        $inbox=(string)ComponentHelper::getParams('com_dharmashop')->get('import_inbox','files/import/inbox');
        if(!preg_match('~^files/[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*$~D',$inbox)){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_PATH');}
        $directory=JPATH_ROOT;foreach(explode('/',$inbox) as $part){$directory.='/'.$part;if(is_link($directory)){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_PATH');}}
        $base=realpath(JPATH_ROOT.'/'.$inbox);$name=(string)($p['source_path']??'');
        if($name===''||str_contains($name,'..')||str_contains($name,'\\')||str_contains($name,"\0")){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_PATH');}
        $path=$base?realpath($base.'/'.$name):false;
        if(!$base||!$path||!str_starts_with($path,$base.'/')||!is_file($path)||is_link($base.'/'.$name)){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_PATH');}
        $s=$this->source($path,basename($path));return $this->file($s['id']);
    }
    public function start(int $profileId): int
    {
        Access::require('dharmashop.import.check');$profile=Profile::prepare($this->profile($profileId),$this->db);
        if(!$profile['state']){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_DISABLED');}
        $active=(int)$this->db->setQuery('SELECT id FROM #__dharmashop_import_jobs WHERE active_profile='.$profileId)->loadResult();
        if($active){
            $existing=$this->job($active);Access::job($existing);
            // Проверка, которую не подтвердили, заменяется новой (файл и профиль могли измениться); выполняющийся и прерванный запуск продолжается.
            if($existing['state']!=='checked'){return $active;}
            $this->cancel($active);
        }
        $source=$this->receive($profile);$readerSource=$this->readerSource($profile,$source);$readerParams=$profile['params'];if($profile['format']==='xlsx'){$readerParams['separator']='auto';$readerParams['encoding']='UTF-8';}$sample=(new CsvReader($readerParams+['storage_folder'=>$readerSource['folder']]))->sample($readerSource['token']);
        if(($profile['params']['headers']??null)!==$sample['headers']){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_HEADERS_CHANGED');}
        $targets=array_column($profile['params']['mapping'],'target');if(!in_array($profile['params']['key'],$targets,true)){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_KEY_ERROR');}
        $snapshot=['profile'=>$profile,'source'=>$source,'reader_source'=>$readerSource,'sample'=>$sample];$now=gmdate('Y-m-d H:i:s');
        $job=(object)['profile_id'=>$profileId,'active_profile'=>$profileId,'state'=>'reading','snapshot'=>json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'cursor'=>$sample['cursor'],'created_by'=>(int)Factory::getApplication()->getIdentity()->id,'created'=>$now,'updated'=>$now];
        $this->db->insertObject('#__dharmashop_import_jobs',$job,'id');return (int)$job->id;
    }
    public function approve(int $id): void
    {
        Access::require('dharmashop.import.apply');$job=$this->job($id);Access::job($job);
        if($job['state']!=='checked'||($job['errors']&&!$job['snapshot']['profile']['params']['skip_errors'])){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_NOT_CHECKED');}
        $this->db->setQuery("UPDATE #__dharmashop_import_jobs SET state='applying',approved_by=".(int)Factory::getApplication()->getIdentity()->id.",approved_at=UTC_TIMESTAMP(),`cursor`=0,processed=0,error=NULL WHERE id=".$id." AND state='checked' AND (lease_until IS NULL OR lease_until<UTC_TIMESTAMP())")->execute();
        if(!$this->db->getAffectedRows()){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_BUSY');}
    }
    public function resume(int $id): void
    {
        $j=$this->job($id);Access::job($j);$phase=$j['snapshot']['resume_phase']??'';
        Access::require($phase==='applying'?'dharmashop.import.apply':'dharmashop.import.check');
        if($j['state']!=='failed'||!in_array($phase,['reading','checking','media','finalizing','applying'],true)){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_INVALID');}
        $this->db->setQuery('UPDATE #__dharmashop_import_jobs SET state='.$this->db->quote($phase).",error=NULL WHERE id=".$id." AND state='failed' AND (lease_until IS NULL OR lease_until<UTC_TIMESTAMP())")->execute();
        if(!$this->db->getAffectedRows()){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_BUSY');}
    }
    public function cancel(int $id): void
    {
        $j=$this->job($id);Access::job($j);Access::require(($j['state']==='applying'||($j['state']==='failed'&&($j['snapshot']['resume_phase']??'')==='applying'))?'dharmashop.import.apply':'dharmashop.import.check');
        $this->db->setQuery("UPDATE #__dharmashop_import_jobs SET state='cancelled',active_profile=NULL,finished=UTC_TIMESTAMP() WHERE state NOT IN ('done','cancelled') AND id=".$id.' AND (lease_until IS NULL OR lease_until<UTC_TIMESTAMP())')->execute();
        if(!$this->db->getAffectedRows()){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_BUSY');}
    }
    /** Строки отчёта; filter: error | create | update | unchanged (пустой — все). */
    public function rows(int $job, int $after = 0, int $limit = 100, string $filter = ''): array
    {
        $where = match ($filter) {
            'error' => " AND state='error'",
            'create', 'update' => ' AND state<>\'error\' AND action=' . $this->db->quote($filter),
            'unchanged' => " AND state<>'error' AND action IN ('skip','unchanged')",
            default => '',
        };

        return $this->db->setQuery('SELECT id,line,state,source_key,target_id,action,payload,error FROM #__dharmashop_import_rows WHERE job_id=' . $job . ' AND id>' . $after . $where . ' ORDER BY id', 0, max(1, min(100, $limit)))->loadAssocList();
    }

    /** Сводка запуска: сколько записей будет создано, обновлено, не изменится, и сколько строк с ошибками. */
    public function summary(int $job): array
    {
        $s = ['create' => 0, 'update' => 0, 'unchanged' => 0, 'errors' => 0, 'rows' => 0];
        foreach ($this->db->setQuery('SELECT state,COALESCE(action,\'\') AS action,COUNT(*) AS c FROM #__dharmashop_import_rows WHERE job_id=' . $job . ' GROUP BY state,action')->loadAssocList() as $r) {
            $c = (int) $r['c'];
            $s['rows'] += $c;
            if ($r['state'] === 'error') {
                $s['errors'] += $c;
            } elseif ($r['action'] === 'create') {
                $s['create'] += $c;
            } elseif ($r['action'] === 'update') {
                $s['update'] += $c;
            } elseif (in_array($r['action'], ['skip', 'unchanged'], true)) {
                $s['unchanged'] += $c;
            }
        }

        return $s;
    }

    /** Файл запуска (нормализованная копия, медиа, копия источника по ссылке или из папки) удаляется вместе с записью. */
    private function dropFile(int $id): void
    {
        $file = $this->db->setQuery('SELECT token,folder FROM #__dharmashop_import_files WHERE id=' . $id)->loadAssoc();
        if ($file && $file['token']) {
            try {
                $path = (new Storage($file['folder']))->path((string) $file['token']);
                if (is_file($path) && !is_link($path)) {
                    unlink($path);
                }
            } catch (\Throwable) {
            }
        }
        $this->db->setQuery('DELETE FROM #__dharmashop_import_files WHERE id=' . $id)->execute();
    }

    /** Удаляет запуск, его строки и файлы. Выполняющийся прямо сейчас запуск (действующая аренда) не удаляется; файл, на который ссылается профиль, остаётся. */
    public function remove(int $id): bool
    {
        $job = $this->job($id);
        Access::job($job);
        Access::require('dharmashop.import.report');
        if ($job['lease_until'] && strtotime($job['lease_until'] . ' UTC') > time()) {
            return false;
        }
        $keep = (int) ($job['snapshot']['profile']['params']['source_id'] ?? 0);
        $profile = $this->db->setQuery('SELECT params FROM #__dharmashop_import_profiles WHERE id=' . (int) $job['profile_id'])->loadResult();
        if ($profile) {
            $keep = max($keep, (int) (json_decode($profile, true)['source_id'] ?? 0));
        }
        $files = array_map('intval', $this->db->setQuery('SELECT id FROM #__dharmashop_import_files WHERE job_id=' . $id)->loadColumn());
        foreach (['reader_source', 'source'] as $key) {
            $fid = (int) ($job['snapshot'][$key]['id'] ?? 0);
            if ($fid && $fid !== $keep && !in_array($fid, $files, true)) {
                $files[] = $fid;
            }
        }
        foreach ($files as $fid) {
            $this->dropFile($fid);
        }
        $this->db->setQuery('DELETE FROM #__dharmashop_import_rows WHERE job_id=' . $id)->execute();
        $this->db->setQuery('DELETE FROM #__dharmashop_import_jobs WHERE id=' . $id)->execute();

        return true;
    }

    /** Очистка истории: завершённые и отменённые запуски. Незавершённые, ждущие подтверждения и прерванные остаются. */
    public function clear(): int
    {
        $n = 0;
        foreach ($this->db->setQuery("SELECT id FROM #__dharmashop_import_jobs WHERE state IN ('done','cancelled') ORDER BY id")->loadColumn() as $id) {
            if ($this->remove((int) $id)) {
                $n++;
            }
        }

        return $n;
    }
    public function step(int $id): array
    {
        $job=$this->job($id);Access::job($job);
        if(!in_array($job['state'],['reading','checking','media','finalizing','applying'],true)){return $job;}
        Access::require($job['state']==='applying'?'dharmashop.import.apply':'dharmashop.import.check');
        $token=bin2hex(random_bytes(24));$this->db->setQuery('UPDATE #__dharmashop_import_jobs SET lease='.$this->db->quote($token).',lease_until=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 300 SECOND) WHERE id='.$id.' AND state='.$this->db->quote($job['state']).' AND (lease_until IS NULL OR lease_until<UTC_TIMESTAMP())')->execute();
        if(!$this->db->getAffectedRows()){return $this->job($id);}
        $lock='dharma.import.'.$job['snapshot']['profile']['entity'];$locked=false;
        try{
            if($job['state']==='applying'){$locked=(bool)$this->db->setQuery('SELECT GET_LOCK('.$this->db->quote($lock).',0)')->loadResult();if(!$locked){return $this->job($id);}}
            $this->work($job);
        }catch(\Throwable $e){
            Log::addLogger(['text_file'=>'com_dharmashop_import.log.php'],Log::ALL,['com_dharmashop_import']);Log::add($e::class.': '.$e->getMessage(),Log::ERROR,'com_dharmashop_import');
            $snapshot=$job['snapshot'];$snapshot['resume_phase']=$job['state'];
            $this->save('#__dharmashop_import_jobs',$id,['state'=>'failed','error'=>self::message($e),'snapshot'=>json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
        }finally{
            if($locked){$this->db->setQuery('DO RELEASE_LOCK('.$this->db->quote($lock).')')->execute();}
            $this->db->setQuery('UPDATE #__dharmashop_import_jobs SET lease=NULL,lease_until=NULL,updated=UTC_TIMESTAMP() WHERE id='.$id.' AND lease='.$this->db->quote($token))->execute();
        }return $this->job($id);
    }
    /** Бюджет времени одного шага: за один запрос окна прогресса делаем столько работы, сколько успеем (аренда шага — 300 с). */
    private const BUDGET = 8;
    private const READ_ROWS = 5000;

    private function work(array $job): void
    {
        $profile = $job['snapshot']['profile'];
        // В режиме отладки Joomla копит каждый запрос в памяти: шаг короче, чтобы не упереться в memory_limit.
        $deadline = microtime(true) + (\defined('JDEBUG') && JDEBUG ? 2 : self::BUDGET);
        match ($job['state']) {
            'reading' => $this->read($job, $profile, $deadline),
            'media' => $this->media($job, $profile, $deadline),
            'checking', 'finalizing' => $this->check($job, $profile, $deadline),
            default => $this->apply($job, $profile, $deadline),
        };
    }

    private function isDuplicateKey(\Throwable $e): bool
    {
        return $e->getCode() === 1062 || str_contains($e->getMessage(), 'Duplicate entry');
    }

    /** Строка исходного файла попадает в очередь один раз; повторное чтение той же строки после обрыва допустимо, если она не изменилась. */
    private function insertRow(int $job, int $line, string $state, string $payload, ?string $error): void
    {
        $row = (object) ['job_id' => $job, 'line' => $line, 'state' => $state, 'payload' => $payload, 'error' => $error];
        try {
            $this->db->insertObject('#__dharmashop_import_rows', $row, 'id');
        } catch (\Throwable $e) {
            if (!$this->isDuplicateKey($e)) {
                throw $e;
            }
            $existing = $this->db->setQuery('SELECT payload FROM #__dharmashop_import_rows WHERE job_id=' . $job . ' AND line=' . $line)->loadResult();
            if ($existing !== $payload) {
                throw new \RuntimeException('COM_DHARMASHOP_IMPORT_SOURCE_CHANGED');
            }
        }
    }

    private function read(array $job, array $profile, float $deadline): void
    {
        $id = (int) $job['id'];
        $sample = $job['snapshot']['sample'];
        $width = count($sample['headers']);
        $readerParams = $profile['params'];
        if ($profile['format'] === 'xlsx') {
            $readerParams['separator'] = ',';
            $readerParams['encoding'] = 'UTF-8';
        }
        $reader = new CsvReader($readerParams);
        $source = $job['snapshot']['reader_source'] ?? $job['snapshot']['source'];
        $stream = (new Storage($source['folder']))->stream($source['token']);
        fseek($stream, (int) $job['cursor']);
        $line = (int) $job['line'];
        $eof = false;
        $position = (int) $job['cursor'];
        try {
            for ($n = 0; $n < self::READ_ROWS && microtime(true) < $deadline; $n++) {
                try {
                    $raw = $reader->row($stream, $sample['separator']);
                } catch (\RuntimeException $e) {
                    // Строка слишком длинная или в другой кодировке: ошибка этой строки, остальной файл читается дальше.
                    $line++;
                    $this->insertRow($id, $line, 'error', '[]', self::message($e));
                    continue;
                }
                if ($raw === false) {
                    $eof = true;
                    break;
                }
                $line++;
                if (!array_filter($raw, static fn ($cell) => $cell !== '')) {
                    continue; // пустая строка или строка из одних разделителей
                }
                $state = 'raw';
                $error = null;
                if (count($raw) > $width) {
                    $state = 'error';
                    $error = 'COM_DHARMASHOP_IMPORT_COLUMNS';
                } elseif (count($raw) < $width) {
                    $raw = array_pad($raw, $width, ''); // недостающие хвостовые ячейки считаются пустыми
                }
                $this->insertRow($id, $line, $state, json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $error);
            }
            $position = (int) ftell($stream);
        } finally {
            fclose($stream);
        }
        $total = (int) $this->db->setQuery('SELECT COUNT(*) FROM #__dharmashop_import_rows WHERE job_id=' . $id)->loadResult();
        $errors = (int) $this->db->setQuery("SELECT COUNT(*) FROM #__dharmashop_import_rows WHERE job_id=" . $id . " AND state='error'")->loadResult();
        $this->save('#__dharmashop_import_jobs', $id, ['cursor' => $eof ? 0 : $position, 'line' => $line, 'total' => $total, 'errors' => $errors, 'state' => $eof ? 'checking' : 'reading', 'processed' => 0]);
    }

    private function media(array $job, array $profile, float $deadline): void
    {
        $id = (int) $job['id'];
        $limit = max(16, min(10240, (int) ComponentHelper::getParams('com_dharmashop')->get('import_media_max_mb', 512))) * 1048576;
        $perFile = max(1, min(64, (int) ComponentHelper::getParams('com_dharmashop')->get('import_media_file_mb', 16))) * 1048576;
        $finished = false;
        // Следующий файл начинаем, пока есть запас времени: одно скачивание занимает до 25 с.
        while (microtime(true) < $deadline) {
            $file = $this->db->setQuery("SELECT id,url,kind FROM #__dharmashop_import_files WHERE job_id=" . $id . " AND state='pending' ORDER BY id", 0, 1)->loadAssoc();
            if (!$file) {
                $finished = true;
                break;
            }
            try {
                $used = (int) $this->db->setQuery("SELECT COALESCE(SUM(bytes),0) FROM #__dharmashop_import_files WHERE job_id=" . $id)->loadResult();
                if ($used >= $limit) {
                    throw new \RuntimeException('COM_DHARMASHOP_IMPORT_QUOTA');
                }
                $response = Remote::get($file['url'], $profile['params']['media_hosts'], !empty($profile['params']['allow_http']), min($perFile, $limit - $used));
                try {
                    $stored = (new Storage())->put($response['path']);
                    $this->save('#__dharmashop_import_files', (int) $file['id'], $stored + ['state' => 'ready', 'mime' => $response['mime'], 'name' => mb_substr($response['name'], 0, 255)]);
                } finally {
                    unlink($response['path']);
                }
            } catch (\Throwable $e) {
                $this->save('#__dharmashop_import_files', (int) $file['id'], ['state' => 'failed', 'error' => self::message($e)]);
            }
        }
        $all = (int) $this->db->setQuery('SELECT COUNT(*) FROM #__dharmashop_import_files WHERE job_id=' . $id)->loadResult();
        $left = (int) $this->db->setQuery("SELECT COUNT(*) FROM #__dharmashop_import_files WHERE job_id=" . $id . " AND state='pending'")->loadResult();
        if ($finished || $left === 0) {
            $rows = (int) $this->db->setQuery('SELECT COUNT(*) FROM #__dharmashop_import_rows WHERE job_id=' . $id)->loadResult();
            $this->save('#__dharmashop_import_jobs', $id, ['state' => 'finalizing', 'processed' => 0, 'total' => $rows]);
            return;
        }
        // На время скачивания полоса прогресса показывает файлы, а не строки.
        $this->save('#__dharmashop_import_jobs', $id, ['total' => $all, 'processed' => $all - $left]);
    }

    /** Две строки плана одинаковы по смыслу: одна и та же запись с тем же результатом. */
    private function samePlan(array $a, array $b): bool
    {
        return ($a['target'] ?? 0) === ($b['target'] ?? 0) && ($a['action'] ?? '') === ($b['action'] ?? '')
            && json_encode($a['patch'] ?? []) === json_encode($b['patch'] ?? []);
    }

    private function check(array $job, array $profile, float $deadline): void
    {
        $id = (int) $job['id'];
        $wanted = $job['state'] === 'checking' ? 'raw' : 'planned';
        $records = new Records($this->db);
        while (microtime(true) < $deadline) {
            $rows = $this->db->setQuery('SELECT id,line,payload FROM #__dharmashop_import_rows WHERE job_id=' . $id . ' AND state=' . $this->db->quote($wanted) . ' ORDER BY id', 0, 200)->loadAssocList();
            if (!$rows) {
                break;
            }
            foreach ($rows as $row) {
                try {
                    if ($wanted === 'raw') {
                        $this->plan($id, $profile, $records, $row);
                    } else {
                        $plan = json_decode($row['payload'], true, 64, JSON_THROW_ON_ERROR);
                        $records->validateAssets($plan);
                        $this->parents($id, $profile, $plan);
                        $this->save('#__dharmashop_import_rows', (int) $row['id'], ['state' => 'ready']);
                    }
                } catch (\Throwable $e) {
                    if ($this->infrastructure($e)) {
                        throw $e;
                    }
                    $this->save('#__dharmashop_import_rows', (int) $row['id'], ['state' => 'error', 'error' => self::message($e)]);
                }
                if (microtime(true) > $deadline) {
                    break 2;
                }
            }
        }
        $remaining = (int) $this->db->setQuery('SELECT COUNT(*) FROM #__dharmashop_import_rows WHERE job_id=' . $id . ' AND state=' . $this->db->quote($wanted))->loadResult();
        $errors = (int) $this->db->setQuery("SELECT COUNT(*) FROM #__dharmashop_import_rows WHERE job_id=" . $id . " AND state='error'")->loadResult();
        $values = ['processed' => (int) $job['total'] - $remaining, 'errors' => $errors];
        if (!$remaining) {
            $values['state'] = $wanted === 'raw' ? 'media' : 'checked';
            $values['processed'] = 0;
        }
        $this->save('#__dharmashop_import_jobs', $id, $values);
    }

    /** План одной строки: разбор значений, права, поиск дублей ключа и цели. Одинаковые повторы строки сливаются, расходящиеся — ошибка обеим. */
    private function plan(int $id, array $profile, Records $records, array $row): void
    {
        $plan = $records->prepare($profile, json_decode($row['payload'], true, 64, JSON_THROW_ON_ERROR), $id);
        $records->assertAccess($profile, $plan['target'], $plan['patch']);
        $hash = $this->keyHash($plan['key']);
        $duplicate = $this->db->setQuery('SELECT id,payload FROM #__dharmashop_import_rows WHERE job_id=' . $id . ' AND key_hash=' . $this->db->quote($hash) . ' AND id<>' . (int) $row['id'], 0, 1)->loadAssoc();
        if (!$duplicate && $plan['target']) {
            $duplicate = $this->db->setQuery('SELECT id,payload FROM #__dharmashop_import_rows WHERE job_id=' . $id . ' AND target_id=' . (int) $plan['target'] . ' AND key_hash IS NOT NULL AND id<>' . (int) $row['id'], 0, 1)->loadAssoc();
        }
        if ($duplicate) {
            $other = json_decode((string) $duplicate['payload'], true) ?: [];
            if (($other['key'] ?? null) !== null && $this->samePlan($other, $plan)) {
                $skip = ['key' => $plan['key'], 'target' => $plan['target'], 'action' => 'skip', 'patch' => [], 'before' => [], 'assets' => [], 'duplicate_of' => (int) $duplicate['id']];
                $this->save('#__dharmashop_import_rows', (int) $row['id'], ['state' => 'planned', 'source_key' => $plan['key'], 'key_hash' => null, 'target_id' => $plan['target'], 'action' => 'skip', 'payload' => json_encode($skip, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
                return;
            }
            $this->save('#__dharmashop_import_rows', (int) $duplicate['id'], ['state' => 'error', 'error' => 'COM_DHARMASHOP_IMPORT_DUPLICATE']);
            $this->save('#__dharmashop_import_rows', (int) $row['id'], ['source_key' => $plan['key']]);
            throw new \RuntimeException('COM_DHARMASHOP_IMPORT_DUPLICATE');
        }
        $this->save('#__dharmashop_import_rows', (int) $row['id'], ['state' => 'planned', 'source_key' => $plan['key'], 'key_hash' => $hash, 'target_id' => $plan['target'], 'action' => $plan['action'], 'payload' => json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
    }

    /** Сбои базы данных и ошибки кода останавливают запуск; ошибка данных одной строки — только этой строки. */
    private function infrastructure(\Throwable $e): bool
    {
        return !($e instanceof \RuntimeException || $e instanceof \InvalidArgumentException || $e instanceof \DomainException || $e instanceof \UnexpectedValueException)
            || $e instanceof \Joomla\Database\Exception\DatabaseExceptionInterface || $e instanceof \mysqli_sql_exception;
    }

    private function apply(array $job, array $profile, float $deadline): void
    {
        $id = (int) $job['id'];
        $records = new Records($this->db);
        $cursor = (int) $job['cursor'];
        $exhausted = false;
        while (microtime(true) < $deadline) {
            $rows = $this->db->setQuery("SELECT id,payload,state FROM #__dharmashop_import_rows WHERE job_id=" . $id . " AND state IN ('ready','writing') AND id>" . $cursor . ' ORDER BY id', 0, 50)->loadAssocList();
            if (!$rows) {
                $exhausted = true;
                break;
            }
            foreach ($rows as $row) {
                $cursor = (int) $row['id'];
                try {
                    $this->applyRow($id, $profile, $records, $row);
                } catch (\Throwable $e) {
                    if ($this->infrastructure($e)) {
                        throw $e;
                    }
                    // Строка не записана или записана частично: она помечается ошибкой, остальные строки продолжают применяться.
                    $this->save('#__dharmashop_import_rows', (int) $row['id'], ['state' => 'error', 'error' => self::message($e)]);
                }
                if (microtime(true) > $deadline) {
                    break 2;
                }
            }
            $this->save('#__dharmashop_import_jobs', $id, ['cursor' => $cursor]);
        }
        $count = ['pending' => 0, 'done' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'errors' => 0];
        foreach ($this->db->setQuery('SELECT state,action,COUNT(*) AS c FROM #__dharmashop_import_rows WHERE job_id=' . $id . ' GROUP BY state,action')->loadAssocList() as $r) {
            $c = (int) $r['c'];
            if (in_array($r['state'], ['ready', 'writing'], true)) {
                $count['pending'] += $c;
            } elseif ($r['state'] === 'error') {
                $count['errors'] += $c;
            } elseif ($r['state'] === 'done') {
                $count['done'] += $c;
                $count['created'] += $r['action'] === 'create' ? $c : 0;
                $count['updated'] += $r['action'] === 'update' ? $c : 0;
                $count['unchanged'] += in_array($r['action'], ['skip', 'unchanged'], true) ? $c : 0;
            }
        }
        $values = ['cursor' => $cursor, 'processed' => $count['done'], 'created_count' => $count['created'], 'updated_count' => $count['updated'], 'unchanged_count' => $count['unchanged'], 'errors' => $count['errors']];
        if (!$count['pending']) {
            $values += ['state' => 'done', 'active_profile' => null, 'finished' => gmdate('Y-m-d H:i:s')];
        } elseif ($exhausted) {
            // Проход закончился, но остались строки, ждущие родителя (дерево идёт не по порядку): следующий проход с начала.
            $snapshot = $job['snapshot'];
            if ($count['done'] === (int) ($snapshot['pass_done'] ?? -1)) {
                // За целый проход ни одна строка не продвинулась: родители этих строк не создаются — это ошибка самих строк, а не запуска.
                $this->db->setQuery("UPDATE #__dharmashop_import_rows SET state='error',error='COM_DHARMASHOP_IMPORT_CATEGORY' WHERE job_id=" . $id . " AND state IN ('ready','writing')")->execute();
                $values['errors'] = $count['errors'] + $count['pending'];
                $values += ['state' => 'done', 'active_profile' => null, 'finished' => gmdate('Y-m-d H:i:s')];
            } else {
                $snapshot['pass_done'] = $count['done'];
                $values += ['cursor' => 0, 'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)];
                $values['cursor'] = 0;
            }
        }
        $this->save('#__dharmashop_import_jobs', $id, $values);
    }

    /** Одна строка плана: сверка с актуальным состоянием, зависимости, запись через модель и внешнее соответствие. */
    private function applyRow(int $job, array $profile, Records $records, array $row): void
    {
        try {
            $this->applyRowPlan($job, $profile, $records, $row);
        } finally {
            $records->forget();
        }
    }

    private function applyRowPlan(int $job, array $profile, Records $records, array $row): void
    {
        $plan = json_decode($row['payload'], true, 64, JSON_THROW_ON_ERROR);
        $target = (int) $plan['target'];
        if (isset($plan['patch']['__parent_key'])) {
            $parent = $records->find($profile, $plan['patch']['__parent_key']);
            if (!$parent) {
                return; // родитель ещё не создан: строка останется в очереди до следующего прохода
            }
            $plan['patch']['parent_id'] = $parent;
            unset($plan['patch']['__parent_key']);
        }
        if (!in_array($plan['action'], ['skip', 'unchanged'], true)) {
            $alreadyWritten = false;
            if ($row['state'] === 'writing') {
                if (!$target) {
                    $target = $records->recover($profile, $plan);
                    $alreadyWritten = $target > 0;
                } elseif ($records->matches($profile, $target, $plan['patch'])) {
                    $alreadyWritten = true;
                } elseif (!$records->matches($profile, $target, $plan['before'])) {
                    throw new \RuntimeException('COM_DHARMASHOP_IMPORT_RECOVERY');
                }
            }
            if (!$alreadyWritten) {
                if ($row['state'] !== 'writing') {
                    if ($records->find($profile, $plan['key'], $plan['patch']) !== (int) $plan['target']) {
                        throw new \RuntimeException('COM_DHARMASHOP_IMPORT_STALE');
                    }
                    $plan = (new Dependencies($this->db))->resolve($profile, $plan, $records);
                    $plan = $records->uniqueAlias($profile, $plan);
                    $plan = $records->promote($profile, $plan);
                    $this->save('#__dharmashop_import_rows', (int) $row['id'], ['state' => 'writing', 'payload' => json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
                }
                $target = $records->apply($profile, $plan);
            }
            if ($profile['params']['key'] === 'external_id') {
                $existing = $records->find($profile, $plan['key']);
                if ($existing && $existing !== $target) {
                    throw new \RuntimeException('COM_DHARMASHOP_IMPORT_CONFLICT');
                }
                if (!$existing) {
                    $link = (object) ['entity' => $profile['entity'], 'source' => $profile['params']['source_code'], 'external_id' => $plan['key'], 'id' => $target];
                    $this->db->insertObject('#__dharmashop_external_ids', $link);
                }
            }
        }
        $this->save('#__dharmashop_import_rows', (int) $row['id'], ['state' => 'done', 'target_id' => $target]);
    }

    private function parents(int $job, array $profile, array $plan): void
    {
        $key=$plan['patch']['__parent_key']??null;$seen=[$plan['key']=>true];
        for($depth=0;$key!==null;$depth++){
            if($depth>100||isset($seen[$key])){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_CATEGORY');}$seen[$key]=true;
            if((new Records($this->db))->find($profile,$key)){break;}
            $row=$this->db->setQuery('SELECT payload,state FROM #__dharmashop_import_rows WHERE job_id='.$job.' AND key_hash='.$this->db->quote($this->keyHash($key)))->loadAssoc();
            if(!$row||$row['state']==='error'){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_CATEGORY');}$parent=json_decode($row['payload'],true);$key=$parent['patch']['__parent_key']??null;
        }
    }
    public static function message(\Throwable $e): string
    {
        $message=$e->getMessage();
        if(preg_match('/^(?:COM_[A-Z0-9_]+|PLG_[A-Z0-9_]+|J[A-Z0-9_]+)$/D',$message)){return $message;}
        if($e instanceof \InvalidArgumentException&&mb_check_encoding($message,'UTF-8')){return mb_substr(preg_replace('/[\x00-\x1f\x7f]+/',' ',strip_tags($message)),0,600);}
        return 'COM_DHARMASHOP_IMPORT_FAILURE';
    }
}
