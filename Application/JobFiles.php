<?php

namespace App\GP247\Plugins\DataExport\Application;

/**
 * Where the file of a run lives: storage/app/data-export (outside the web root), named by the
 * run id only — `<id>.csv.part` while it is built, `<id>.csv` once ready.
 *
 * @aidlc-unit data-export
 * @aidlc-story US-data-export-download-retention
 */
final class JobFiles
{
    /**
     * Folder of the files.
     *
     * @return string
     */
    public static function dir(): string
    {
        return storage_path('app' . DIRECTORY_SEPARATOR . 'data-export');
    }

    /**
     * Create the folder when missing.
     *
     * @return void
     */
    public static function ensureDir(): void
    {
        if (!is_dir(self::dir())) {
            mkdir(self::dir(), 0750, true);
        }
    }

    /**
     * File of a ready run.
     *
     * @param string $id Run id (checked: lower-case ULID).
     * @return string
     */
    public static function path(string $id): string
    {
        return self::dir() . DIRECTORY_SEPARATOR . self::safe($id) . '.csv';
    }

    /**
     * File of a run being built.
     *
     * @param string $id
     * @return string
     */
    public static function partPath(string $id): string
    {
        return self::path($id) . '.part';
    }

    /**
     * Remove both files of a run (missing files are fine).
     *
     * @param string $id
     * @return void
     */
    public static function delete(string $id): void
    {
        foreach ([self::path($id), self::partPath($id)] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    /**
     * Run ids that have a file in the folder.
     *
     * @return list<string>
     */
    public static function idsOnDisk(): array
    {
        $ids = [];
        foreach (glob(self::dir() . DIRECTORY_SEPARATOR . '*.csv*') ?: [] as $file) {
            if (preg_match('/^([0-9a-z]{26})\.csv(\.part)?$/', basename($file), $m)) {
                $ids[$m[1]] = $m[1];
            }
        }

        return array_values($ids);
    }

    /**
     * @param string $id
     * @return string
     * @throws \InvalidArgumentException When $id is not a run id (no path can be built from it).
     */
    private static function safe(string $id): string
    {
        if (!preg_match('/^[0-9a-z]{26}$/', $id)) {
            throw new \InvalidArgumentException('Not a run id.');
        }

        return $id;
    }
}
