<?php

namespace App\GP247\Plugins\DataExport\Domain;

/**
 * Character encoding of a file: UTF-8 with a byte order mark (Excel opens it right in every
 * language — the default) or Shift_JIS / CP932 for Japanese accounting software.
 *
 * @aidlc-unit data-export
 * @aidlc-story US-data-export-excel-encoding
 */
final class TargetEncoding
{
    public const UTF8_BOM = 'utf8_bom';
    public const CP932 = 'cp932';

    /**
     * Encodings this host can write.
     *
     * @return list<string>
     */
    public static function available(): array
    {
        return self::cp932Available() ? [self::UTF8_BOM, self::CP932] : [self::UTF8_BOM];
    }

    /**
     * Whether mbstring of this host converts to CP932.
     *
     * @return bool
     */
    public static function cp932Available(): bool
    {
        return function_exists('mb_list_encodings') && in_array('CP932', mb_list_encodings(), true);
    }

    /**
     * Charset name of the Content-Type header.
     *
     * @param string $encoding
     * @return string
     */
    public static function charset(string $encoding): string
    {
        return $encoding === self::CP932 ? 'Shift_JIS' : 'UTF-8';
    }
}
