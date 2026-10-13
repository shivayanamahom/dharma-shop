<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShopImport\Administrator\Import;
\defined('_JEXEC') or die;

final class CsvReader
{
    public function __construct(private array $settings) {}
    public function separator($stream): string
    {
        $choice = $this->settings['separator'] ?? 'auto';
        if ($choice !== 'auto') { return $choice === 'tab' ? "\t" : $choice; }
        $start = ftell($stream); $scores = [];
        foreach ([';',',',"\t"] as $separator) {
            fseek($stream,$start); $widths=[];
            for($i=0;$i<5 && ($row=fgetcsv($stream,1048577,$separator,'"',''))!==false;$i++){ if($row!==[null]){$widths[]=count($row);} }
            $scores[$separator] = $widths ? min($widths) : 0;
        }
        fseek($stream,$start); arsort($scores); $best=(int)reset($scores);
        if($best<=1 || count(array_filter($scores,static fn($score)=>$score===$best))!==1){ throw new \RuntimeException('COM_DHARMASHOP_IMPORT_SEPARATOR_ERROR'); }
        return (string)array_key_first($scores);
    }
    public function row($stream, string $separator): array|false
    {
        $start=ftell($stream); $row=fgetcsv($stream,0,$separator,'"','');
        if($row===false){return false;}
        if(ftell($stream)-$start>1048576 || count($row)>200){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_ROW_SIZE');}
        $encoding=$this->settings['encoding']??'UTF-8';
        foreach($row as &$cell){$cell=(string)$cell; if($encoding==='Windows-1251'){$cell=iconv('Windows-1251','UTF-8',$cell);} if(!mb_check_encoding($cell,'UTF-8')||str_contains($cell,"\0")){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_ENCODING_ERROR');}}unset($cell);
        return $row;
    }
    public function sample(string $token): array
    {
        $stream=(new Storage($this->settings['storage_folder']??null))->stream($token);
        try {
            $bom=fread($stream,3); if($bom!=="\xEF\xBB\xBF"){fseek($stream,strlen(Storage::GUARD));}
            // Шапка файла над таблицей (название, дата): пропускаем заданное число строк до заголовка.
            for($i=0,$skip=max(0,min(1000,(int)($this->settings['skip_rows']??0)));$i<$skip;$i++){if(fgets($stream)===false){break;}}
            $begin=(int)ftell($stream);
            $sep=$this->separator($stream); $first=$this->row($stream,$sep);
            if(!$first){throw new \RuntimeException('COM_DHARMASHOP_IMPORT_EMPTY');}
            $header=!isset($this->settings['header']) || !empty($this->settings['header']);
            $headers=$header?$first:array_map(static fn($i)=>'Column '.$i,range(1,count($first)));
            $cursor=$header?ftell($stream):$begin;
            $example=$header?($this->row($stream,$sep)?:[]):$first;
            return ['headers'=>$headers,'example'=>$example,'separator'=>$sep,'cursor'=>$cursor];
        } finally {fclose($stream);}
    }
}
