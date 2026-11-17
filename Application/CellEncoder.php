<?php

namespace App\GP247\Plugins\DataExport\Application;

use App\GP247\Plugins\DataExport\Domain\TargetEncoding;

/**
 * Turns values into CSV lines a spreadsheet opens safely: text that would run as a formula gets a
 * leading apostrophe (numbers stay), cells are quoted per RFC 4180 with CRLF line ends, and the
 * line is converted to the file's encoding — counting the cells CP932 cannot represent.
 *
 * WHY not fputcsv: its backslash escape character breaks CP932 text whose second byte is 0x5C
 * (ソ, 表, 能 …), and its quoting rules differ from what Data Import reads.
 *
 * @aidlc-unit data-export
 * @aidlc-story US-data-export-formula-safe
 * @aidlc-story US-data-export-excel-encoding
 */
final class CellEncoder
{
    /** Number of cells of the last line() that lost a character to the encoding. */
    private int $replaced = 0;

    /**
     * @param string $encoding A TargetEncoding value.
     */
    public function __construct(private readonly string $encoding)
    {
    }

    /**
     * Bytes that start a file of this encoding.
     *
     * @return string
     */
    public function preamble(): string
    {
        return $this->encoding === TargetEncoding::UTF8_BOM ? "\xEF\xBB\xBF" : '';
    }

    /**
     * One cell, protected against formulas (still UTF-8, not quoted).
     *
     * @param mixed $value
     * @return string
     */
    public function cell($value): string
    {
        $text = $value === null ? '' : (string) $value;
        if ($text !== '' && preg_match('/^[=+\-@\t\r]/', $text) && !preg_match('/^[+-]?\d+(\.\d+)?$/', $text)) {
            return "'" . $text;
        }

        return $text;
    }

    /**
     * One CSV line (with CRLF) in the file encoding.
     *
     * @param array<int, mixed> $values
     * @return string
     */
    public function line(array $values): string
    {
        $this->replaced = 0;
        $cells = [];
        foreach ($values as $value) {
            $cells[] = $this->quote($this->convert($this->cell($value)));
        }

        return implode(',', $cells) . "\r\n";
    }

    /**
     * Cells of the last line() that lost a character to the encoding.
     *
     * @return int
     */
    public function replacedInLastLine(): int
    {
        return $this->replaced;
    }

    /**
     * @param string $text UTF-8.
     * @return string Bytes of the file encoding.
     */
    private function convert(string $text): string
    {
        if ($this->encoding !== TargetEncoding::CP932 || $text === '' || !preg_match('/[^\x00-\x7F]/', $text)) {
            return $text;
        }
        $previous = mb_substitute_character();
        mb_substitute_character(0x3F);
        $bytes = (string) mb_convert_encoding($text, 'CP932', 'UTF-8');
        mb_substitute_character($previous);
        if (mb_convert_encoding($bytes, 'UTF-8', 'CP932') !== $text) {
            $this->replaced++;
        }

        return $bytes;
    }

    /**
     * Quote a cell when it holds a separator, a quote, a line break or edge spaces (RFC 4180).
     *
     * @param string $bytes
     * @return string
     */
    private function quote(string $bytes): string
    {
        if ($bytes === '' || (!preg_match('/[",\r\n]/', $bytes) && trim($bytes, ' ') === $bytes)) {
            return $bytes;
        }

        return '"' . str_replace('"', '""', $bytes) . '"';
    }
}
