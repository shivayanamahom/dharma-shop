<?php
declare(strict_types=1);
namespace Dharma\Plugin\DharmaShopImport\Xlsx;
\defined('_JEXEC') or die;

/**
 * Shared string table of a workbook, read lazily from the archive: strings are parsed only as far as the sheet asks for them
 * and kept in a private temporary file (offsets in memory), so a book with millions of unique strings does not exhaust PHP memory.
 */
final class SharedStrings
{
    private const MAX_STRINGS = 4000000;
    private const MAX_BYTES = 268435456;
    private const MAX_CELL_BYTES = 1048576;

    private ?\XMLReader $reader = null;
    /** @var resource|null */
    private $file = null;
    private string $path = '';
    private array $offset = [];
    private array $length = [];
    private int $bytes = 0;
    private bool $finished;

    public function __construct(private Reader $owner, private string $source, bool $exists)
    {
        $this->finished = !$exists;
    }

    public function get(int $index): string
    {
        while (!$this->finished && !isset($this->offset[$index])) { $this->advance(); }
        if (!isset($this->offset[$index])) { throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_INVALID'); }
        if ($this->length[$index] === 0) { return ''; }
        fseek($this->file, $this->offset[$index]);
        $value = fread($this->file, $this->length[$index]);
        if ($value === false) { throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_READ'); }
        return $value;
    }

    private function open(): void
    {
        $this->reader = $this->owner->stream($this->source, 'xl/sharedStrings.xml');
        $path = tempnam(sys_get_temp_dir(), 'dharma-xlsx-');
        if ($path === false) { throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_READ'); }
        chmod($path, 0600);
        $this->path = $path; $this->file = fopen($path, 'w+b');
        if (!$this->file) { throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_READ'); }
    }

    private function advance(): void
    {
        if ($this->reader === null) { $this->open(); }
        while ($this->reader->read()) {
            if ($this->reader->nodeType !== \XMLReader::ELEMENT || $this->reader->localName !== 'si') { continue; }
            $value = $this->owner->richText($this->reader);
            $length = strlen($value);
            if ($length > self::MAX_CELL_BYTES || ($this->bytes += $length) > self::MAX_BYTES || count($this->offset) >= self::MAX_STRINGS) { throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_LIMIT'); }
            fseek($this->file, 0, SEEK_END);
            $position = (int) ftell($this->file);
            if ($length && fwrite($this->file, $value) !== $length) { throw new \RuntimeException('PLG_DHARMASHOPIMPORT_XLSX_READ'); }
            $this->offset[] = $position; $this->length[] = $length;
            return;
        }
        $this->finished = true;
    }

    public function close(): void
    {
        $this->reader?->close(); $this->reader = null;
        if (is_resource($this->file)) { fclose($this->file); }
        if ($this->path !== '') { @unlink($this->path); $this->path = ''; }
    }
}
