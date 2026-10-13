<?php
declare(strict_types=1);
namespace Dharma\Plugin\DharmaShopImport\Xlsx;
\defined('_JEXEC') or die;

/** Bounded XLSX reader. It normalizes one selected worksheet to UTF-8 CSV for the shared import pipeline. */
final class Reader
{
    private const MAX_XML = 134217728;
    private const MAX_UNPACKED = 536870912;
    private const MAX_SHARED_STRINGS = 250000;
    private const MAX_SHARED_BYTES = 33554432;
    private const MAX_CELL_BYTES = 1048576;

    public function sheets(string $source): array
    {
        $zip = $this->open($source);
        try { return $this->workbook($zip)['sheets']; }
        finally { $zip->close(); }
    }

    /** Returns sheet names and sample information after validating the workbook structure. */
    public function sample(string $source, string $sheet, bool $header = true): array
    {
        if ($sheet === '') { $sheet = $this->sheets($source)[0] ?? ''; }
        $tmp = $this->normalize($source, $sheet, 2);
        try {
            $fh = fopen($tmp, 'rb');
            if (!$fh) { throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_READ'); }
            try {
                $first = fgetcsv($fh, 0, ',', '"', '');
                if (!$first || $first === [null]) { throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_EMPTY'); }
                $headers = $header ? array_map('strval', $first) : array_map(static fn($i) => 'Column '.$i, range(1, count($first)));
                $example = $header ? (fgetcsv($fh, 0, ',', '"', '') ?: []) : $first;
                return ['headers' => $headers, 'example' => array_map('strval', $example), 'sheets' => $this->sheets($source)];
            } finally { fclose($fh); }
        } finally { @unlink($tmp); }
    }

    /** Emits a normalized temporary CSV. Caller must move it into protected import storage. */
    public function normalize(string $source, string $sheet, ?int $rowLimit = null): string
    {
        $zip = $this->open($source); $temps = [];
        try {
            $book = $this->workbook($zip); if ($sheet === '') { $sheet = $book['sheets'][0] ?? ''; } $target = $book['map'][$sheet] ?? null;
            if (!$target) { throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_SHEET'); }
            $shared = $this->sharedStrings($zip, $temps);
            $xmlPath = $this->extract($zip, $target, self::MAX_XML); $temps[] = $xmlPath;
            $reader = $this->xml($xmlPath); $output = tempnam(sys_get_temp_dir(), 'dharma-xlsx-');
            if ($output === false) { throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_READ'); }
            chmod($output, 0600); $out = fopen($output, 'wb');
            if (!$out) { @unlink($output); throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_READ'); }
            $total = 0; $rowCount = 0;
            try {
                while ($reader->read()) {
                    if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->localName !== 'row') { continue; }
                    $row = $this->readRow($reader, $shared);
                    if ($row === []) { continue; }
                    $written = fputcsv($out, $row, ',', '"', '');
                    if ($written === false || ($total += $written) > self::MAX_UNPACKED) { throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_LIMIT'); }
                    $rowCount++;
                    if ($rowLimit !== null && $rowCount >= $rowLimit) { break; }
                }
            } catch (\Throwable $e) { @unlink($output); throw $e; }
            finally { $reader->close(); fclose($out); }
            return $output;
        } finally { $zip->close(); foreach ($temps as $path) { @unlink($path); } }
    }

    private function open(string $source): \ZipArchive
    {
        if (!class_exists(\ZipArchive::class) || !class_exists(\XMLReader::class)) { throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_RUNTIME'); }
        $zip = new \ZipArchive();
        if ($zip->open($source, \ZipArchive::RDONLY) !== true || $zip->numFiles > 2000) { throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_INVALID'); }
        $expanded = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat || str_contains($stat['name'], '../') || str_starts_with($stat['name'], '/') || str_contains($stat['name'], '\\')) { $zip->close(); throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_INVALID'); }
            if (strtolower($stat['name']) === 'xl/vbaproject.bin' || str_starts_with(strtolower($stat['name']), 'xl/externallinks/')) { $zip->close(); throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_INVALID'); }
            $expanded += (int) $stat['size'];
            if ($expanded > self::MAX_UNPACKED) { $zip->close(); throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_LIMIT'); }
        }
        if (!$zip->locateName('[Content_Types].xml') || !$zip->locateName('xl/workbook.xml') || !$zip->locateName('xl/_rels/workbook.xml.rels')) { $zip->close(); throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_INVALID'); }
        return $zip;
    }

    private function workbook(\ZipArchive $zip): array
    {
        $temps = [];
        try {
            $workbook = $this->extract($zip, 'xl/workbook.xml', 1048576); $temps[] = $workbook;
            $rels = $this->extract($zip, 'xl/_rels/workbook.xml.rels', 1048576); $temps[] = $rels;
            $targets = [];$r = $this->xml($rels);
            while ($r->read()) { if ($r->nodeType === \XMLReader::ELEMENT && $r->localName === 'Relationship') { $targets[$r->getAttribute('Id')] = $r->getAttribute('Target'); } }
            $r->close();$names=[];$map=[];$r=$this->xml($workbook);
            while ($r->read()) {
                if ($r->nodeType !== \XMLReader::ELEMENT || $r->localName !== 'sheet') { continue; }
                $name = $r->getAttribute('name');$id = $r->getAttributeNs('id', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');$target = $targets[$id] ?? '';
                if (!$name || !$target || str_contains($target, '..') || str_contains($target, '\\') || str_starts_with($target, '/')) { throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_INVALID'); }
                $path = str_starts_with($target, 'xl/') ? $target : 'xl/'.ltrim($target, '/');
                if (!$zip->locateName($path)) { throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_INVALID'); }
                $names[] = $name;$map[$name] = $path;
                if (count($names) > 100) { throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_LIMIT'); }
            }
            $r->close();if (!$names) { throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_INVALID'); }
            return ['sheets' => $names, 'map' => $map];
        } finally { foreach ($temps as $path) { @unlink($path); } }
    }

    private function sharedStrings(\ZipArchive $zip, array &$temps): array
    {
        $index = $zip->locateName('xl/sharedStrings.xml');if ($index === false) { return []; }
        $path = $this->extract($zip, 'xl/sharedStrings.xml', self::MAX_XML);$temps[] = $path;$r = $this->xml($path);$items=[];$bytes=0;
        try {
            while ($r->read()) {
                if ($r->nodeType !== \XMLReader::ELEMENT || $r->localName !== 'si') { continue; }
                $value = $r->readString();$bytes += strlen($value);
                if (strlen($value) > self::MAX_CELL_BYTES || $bytes > self::MAX_SHARED_BYTES || count($items) >= self::MAX_SHARED_STRINGS) { throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_LIMIT'); }
                $items[] = $value;
            }
        } finally { $r->close(); }
        return $items;
    }

    private function readRow(\XMLReader $r, array $shared): array
    {
        $depth=$r->depth;$cells=[];$last=-1;
        while ($r->read()) {
            if ($r->nodeType === \XMLReader::END_ELEMENT && $r->depth === $depth && $r->localName === 'row') { break; }
            if ($r->nodeType !== \XMLReader::ELEMENT || $r->localName !== 'c') { continue; }
            $reference=$r->getAttribute('r');$type=$r->getAttribute('t')??'';$column=$this->column($reference);$cellDepth=$r->depth;$value='';$formula=false;
            if($r->isEmptyElement){$cells[$column]='';$last=max($last,$column);continue;}
            while ($r->read()) {
                if ($r->nodeType === \XMLReader::END_ELEMENT && $r->depth === $cellDepth && $r->localName === 'c') { break; }
                if ($r->nodeType === \XMLReader::ELEMENT && $r->localName === 'f') { $formula=true; }
                if ($r->nodeType === \XMLReader::ELEMENT && in_array($r->localName,['v','t'],true)) { $value=$r->readString(); }
            }
            if ($formula) { throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_FORMULA'); }
            if ($type === 's') { $i=(int)$value;if(!array_key_exists($i,$shared)){throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_INVALID');}$value=$shared[$i]; }
            elseif ($type === 'b') { $value=$value==='1'?'TRUE':'FALSE'; }
            elseif ($type === 'e') { throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_FORMULA'); }
            if (strlen($value)>self::MAX_CELL_BYTES||!mb_check_encoding($value,'UTF-8')||str_contains($value,"\0")){throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_LIMIT');}
            $cells[$column]=$value;$last=max($last,$column);
        }
        if ($last < 0) { return []; }
        $row=array_fill(0,$last+1,'');foreach($cells as $column=>$value){$row[$column]=$value;}
        return $row;
    }

    private function column(?string $reference): int
    {
        if (!$reference || !preg_match('/^([A-Z]{1,3})[1-9][0-9]{0,6}$/D',$reference,$m)) { throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_INVALID'); }
        $number=0;foreach(str_split($m[1]) as $letter){$number=$number*26+ord($letter)-64;}
        if ($number>200) { throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_LIMIT'); }
        return $number-1;
    }

    private function extract(\ZipArchive $zip, string $name, int $max): string
    {
        $in=$zip->getStream($name);if(!$in){throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_INVALID');}
        $path=tempnam(sys_get_temp_dir(),'dharma-xlsx-');if($path===false){fclose($in);throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_READ');}
        chmod($path,0600);$out=fopen($path,'wb');$size=0;
        try{if(!$out){throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_READ');}while(!feof($in)){$chunk=fread($in,65536);if($chunk===false){throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_READ');}$size+=strlen($chunk);if($size>$max){throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_LIMIT');}if($chunk!==''&&fwrite($out,$chunk)!==strlen($chunk)){throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_READ');}}}
        catch(\Throwable $e){@unlink($path);throw $e;}finally{fclose($in);if(is_resource($out)){fclose($out);}}
        return $path;
    }

    private function xml(string $path): \XMLReader
    {
        $r=new \XMLReader();if(!$r->open($path,null,LIBXML_NONET|LIBXML_COMPACT)){throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_INVALID');}
        $r->setParserProperty(\XMLReader::LOADDTD,false);$r->setParserProperty(\XMLReader::SUBST_ENTITIES,false);return $r;
    }
}
