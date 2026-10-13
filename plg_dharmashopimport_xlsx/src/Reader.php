<?php
declare(strict_types=1);
namespace Dharma\Plugin\DharmaShopImport\Xlsx;
\defined('_JEXEC') or die;

/** Bounded XLSX reader. It normalizes one selected worksheet to UTF-8 CSV for the shared import pipeline. */
final class Reader
{
    private const MAX_XML = 134217728;
    private const MAX_UNPACKED = 536870912;
    private const MAX_CELL_BYTES = 1048576;

    public function sheets(string $source): array
    {
        $zip = $this->open($source);
        try { return $this->workbook($zip)['sheets']; }
        finally { $zip->close(); }
    }

    /** Returns sheet names and sample information after validating the workbook structure. Only the first rows are read from the sheet. */
    public function sample(string $source, string $sheet, bool $header = true, int $skip = 0): array
    {
        if ($sheet === '') { $sheet = $this->sheets($source)[0] ?? ''; }
        $tmp = $this->normalize($source, $sheet, $skip + 2);
        try {
            $fh = fopen($tmp, 'rb');
            if (!$fh) { throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_READ'); }
            try {
                // Шапка листа над таблицей: пропускаем заданное число строк до заголовка (пустые строки листа тоже строки).
                for ($i = 0; $i < max(0, $skip); $i++) { if (fgets($fh) === false) { break; } }
                do { $first = fgetcsv($fh, 0, ',', '"', ''); } while ($first !== false && $first === [null]);
                if (!$first) { throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_EMPTY'); }
                $headers = $header ? array_map('strval', $first) : array_map(static fn($i) => 'Column '.$i, range(1, count($first)));
                $example = [];
                if ($header) { do { $example = fgetcsv($fh, 0, ',', '"', ''); } while ($example !== false && $example === [null]); $example = $example ?: []; } else { $example = $first; }
                return ['headers' => $headers, 'example' => array_map('strval', $example), 'sheets' => $this->sheets($source)];
            } finally { fclose($fh); }
        } finally { @unlink($tmp); }
    }

    /**
     * Emits a normalized temporary CSV. Caller must move it into protected import storage.
     * Rows keep the sheet numbering: a skipped (empty) sheet row becomes an empty CSV line, so "line N" in reports is the Excel row N.
     */
    public function normalize(string $source, string $sheet, ?int $rowLimit = null): string
    {
        $zip = $this->open($source); $store = null; $output = null;
        try {
            $book = $this->workbook($zip); if ($sheet === '') { $sheet = $book['sheets'][0] ?? ''; } $target = $book['map'][$sheet] ?? null;
            if (!$target) { throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_SHEET'); }
            $styles = $this->dateStyles($zip, $source);
            $store = new SharedStrings($this, $source, $zip->locateName('xl/sharedStrings.xml') !== false);
            $reader = $this->stream($source, $target); $output = tempnam(sys_get_temp_dir(), 'dharma-xlsx-');
            if ($output === false) { throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_READ'); }
            chmod($output, 0600); $out = fopen($output, 'wb');
            if (!$out) { @unlink($output); throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_READ'); }
            $total = 0; $position = 0;
            try {
                while ($reader->read()) {
                    if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->localName !== 'row') { continue; }
                    $number = (int) $reader->getAttribute('r');
                    $row = $this->readRow($reader, $store, $styles['xf'], $styles['base']);
                    if ($number <= $position) { $number = $position + 1; }
                    // Пропущенные и пустые строки листа сохраняют нумерацию пустыми строками (ячейки только со стилем в хвосте листа не пишутся).
                    $isEmpty = !array_filter($row, static fn($c) => $c !== '');
                    if ($isEmpty) { continue; }
                    if ($number - $position > 1) { $written = fwrite($out, str_repeat("\n", $number - $position - 1)); $total += (int) $written; }
                    $written = fputcsv($out, $row, ',', '"', '');
                    if ($written === false || ($total += $written) > self::MAX_UNPACKED) { throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_LIMIT'); }
                    $position = $number;
                    if ($rowLimit !== null && $position >= $rowLimit) { break; }
                }
            } catch (\Throwable $e) { @unlink($output); throw $e; }
            finally { $reader->close(); fclose($out); }
            return $output;
        } finally { $store?->close(); $zip->close(); }
    }

    /** XMLReader over a part of the workbook, read as a stream straight from the archive (no full unpacking). */
    public function stream(string $source, string $name): \XMLReader
    {
        $r = new \XMLReader();
        if (!$r->open('zip://'.$source.'#'.$name, null, LIBXML_NONET | LIBXML_COMPACT)) { throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_INVALID'); }
        $r->setParserProperty(\XMLReader::LOADDTD, false); $r->setParserProperty(\XMLReader::SUBST_ENTITIES, false);
        return $r;
    }

    /** Text of an <si> or <is> element: all runs of rich text joined, phonetic hints skipped, _xHHHH_ escapes decoded. */
    public function richText(\XMLReader $r): string
    {
        if ($r->isEmptyElement) { return ''; }
        $depth = $r->depth; $text = ''; $skip = null;
        while ($r->read()) {
            if ($r->nodeType === \XMLReader::END_ELEMENT) {
                if ($r->depth === $depth) { break; }
                if ($skip !== null && $r->depth === $skip) { $skip = null; }
                continue;
            }
            if ($r->nodeType !== \XMLReader::ELEMENT) { continue; }
            if ($r->localName === 'rPh') { if (!$r->isEmptyElement) { $skip = $r->depth; } continue; }
            if ($skip === null && $r->localName === 't' && !$r->isEmptyElement) { $text .= $r->readString(); }
        }
        return self::unescape($text);
    }

    private static function unescape(string $text): string
    {
        if (!str_contains($text, '_x')) { return $text; }
        return preg_replace_callback('/_x([0-9A-Fa-f]{4})_/', static function ($m) {
            $code = hexdec($m[1]);
            if ($code === 0x5F) { return '_'; }
            if ($code < 0x20 && !in_array($code, [9, 10, 13], true)) { return ''; }
            return $code >= 0xD800 && $code <= 0xDFFF ? '' : (mb_chr($code, 'UTF-8') ?: '');
        }, $text) ?? $text;
    }

    /** Which cell styles (cellXfs index) hold dates, and the workbook date base (1900 or 1904 system). */
    private function dateStyles(\ZipArchive $zip, string $source): array
    {
        $base = 25569; // 1970-01-01 в системе дат 1900
        try {
            $r = $this->stream($source, 'xl/workbook.xml');
            while ($r->read()) { if ($r->nodeType === \XMLReader::ELEMENT && $r->localName === 'workbookPr') { if (in_array($r->getAttribute('date1904'), ['1', 'true'], true)) { $base = 24107; } break; } }
            $r->close();
        } catch (\Throwable) {}
        $xf = [];
        if ($zip->locateName('xl/styles.xml') === false) { return ['xf' => $xf, 'base' => $base]; }
        $custom = []; $inXfs = false; $depthXfs = -1; $r = $this->stream($source, 'xl/styles.xml');
        try {
            while ($r->read()) {
                if ($r->nodeType === \XMLReader::ELEMENT && $r->localName === 'numFmt') { $custom[(int) $r->getAttribute('numFmtId')] = (string) $r->getAttribute('formatCode'); }
                elseif ($r->nodeType === \XMLReader::ELEMENT && $r->localName === 'cellXfs') { $inXfs = true; $depthXfs = $r->depth; }
                elseif ($r->nodeType === \XMLReader::END_ELEMENT && $r->localName === 'cellXfs') { break; }
                elseif ($inXfs && $r->nodeType === \XMLReader::ELEMENT && $r->localName === 'xf' && $r->depth === $depthXfs + 1) {
                    $id = (int) $r->getAttribute('numFmtId');
                    $xf[] = self::isDateFormat($id, $custom[$id] ?? null);
                    if (count($xf) > 100000) { throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_LIMIT'); }
                }
            }
        } finally { $r->close(); }
        return ['xf' => $xf, 'base' => $base];
    }

    private static function isDateFormat(int $id, ?string $code): bool
    {
        if ($code === null) { return ($id >= 14 && $id <= 22) || ($id >= 27 && $id <= 36) || ($id >= 45 && $id <= 47) || ($id >= 50 && $id <= 58); }
        $code = preg_replace('/"[^"]*"|\[[^\]]*\]|\\\\.|_.|\*./', '', $code) ?? $code;
        return (bool) preg_match('/[ymdhs]/i', $code);
    }

    private static function serial(string $value, int $base): string
    {
        $days = (float) $value; $seconds = (int) round(($days - $base) * 86400);
        if ($days < 1) { return gmdate('H:i:s', (int) round($days * 86400)); }
        return fmod($days, 1.0) === 0.0 ? gmdate('Y-m-d', $seconds) : gmdate('Y-m-d H:i:s', $seconds);
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
            if (str_ends_with(strtolower($stat['name']), '.xml') && (int) $stat['size'] > self::MAX_XML) { $zip->close(); throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_LIMIT'); }
            $expanded += (int) $stat['size'];
            if ($expanded > self::MAX_UNPACKED) { $zip->close(); throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_LIMIT'); }
        }
        // locateName() даёт индекс записи: у первой записи архива он равен 0, поэтому сравнение строго с false.
        if ($zip->locateName('[Content_Types].xml') === false || $zip->locateName('xl/workbook.xml') === false || $zip->locateName('xl/_rels/workbook.xml.rels') === false) { $zip->close(); throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_INVALID'); }
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
                if ($zip->locateName($path) === false) { throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_INVALID'); }
                $names[] = $name;$map[$name] = $path;
                if (count($names) > 100) { throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_LIMIT'); }
            }
            $r->close();if (!$names) { throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_INVALID'); }
            return ['sheets' => $names, 'map' => $map];
        } finally { foreach ($temps as $path) { @unlink($path); } }
    }

    private function readRow(\XMLReader $r, SharedStrings $shared, array $dateXf, int $base): array
    {
        $depth=$r->depth;$cells=[];$last=-1;
        if ($r->isEmptyElement) { return []; }
        while ($r->read()) {
            if ($r->nodeType === \XMLReader::END_ELEMENT && $r->depth === $depth && $r->localName === 'row') { break; }
            if ($r->nodeType !== \XMLReader::ELEMENT || $r->localName !== 'c') { continue; }
            $reference=$r->getAttribute('r');$type=$r->getAttribute('t')??'';$style=(int)$r->getAttribute('s');$column=$this->column($reference);$cellDepth=$r->depth;$value='';$inline=null;
            if($r->isEmptyElement){continue;}
            while ($r->read()) {
                if ($r->nodeType === \XMLReader::END_ELEMENT && $r->depth === $cellDepth && $r->localName === 'c') { break; }
                if ($r->nodeType !== \XMLReader::ELEMENT) { continue; }
                // Формулы не вычисляются: берётся значение, которое Excel сохранил в ячейке рядом с формулой.
                if ($r->localName === 'v') { $value=$r->readString(); }
                elseif ($r->localName === 'is') { $inline=$this->richText($r); }
            }
            if ($inline !== null) { $value=$inline; }
            elseif ($type === 's') { $value=$value===''?'':$shared->get((int)$value); }
            elseif ($type === 'str') { $value=self::unescape($value); }
            elseif ($type === 'b') { $value=$value==='1'?'TRUE':($value===''?'':'FALSE'); }
            elseif ($type === 'e' || $type === 'd') { /* текст ошибки Excel (#N/A…) или дата ISO остаются как есть: ошибка значения будет видна в строке */ }
            elseif ($value !== '' && is_numeric($value)) {
                if (($dateXf[$style] ?? false)) { $value=self::serial($value,$base); }
                elseif (strlen($value) > 15 && str_contains($value, '.')) { $value=sprintf('%.15G', (float)$value); }
            }
            if (strlen($value)>self::MAX_CELL_BYTES||!mb_check_encoding($value,'UTF-8')||str_contains($value,"\0")){throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_LIMIT');}
            if ($value === '') { continue; }
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
