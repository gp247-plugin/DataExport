<?php

namespace App\GP247\Plugins\DataExport\Application;

use App\GP247\Plugins\DataExport\Domain\ExportKind;
use Illuminate\Support\Facades\DB;

/**
 * Checks the filters of an export at the boundary and returns them normalised (known keys only,
 * empty values dropped, keys sorted). A filter a kind does not have, a date that is not a date,
 * a status, store or category that does not exist is refused with the field it concerns.
 *
 * @aidlc-unit data-export
 * @aidlc-story US-data-export-filters
 */
final class ExportFilter
{
    /** Filters each kind accepts. */
    private const ALLOWED = [
        ExportKind::CATEGORY => ['store', 'status'],
        ExportKind::BRAND => ['store', 'status'],
        ExportKind::SUPPLIER => ['store', 'status'],
        ExportKind::PRODUCT => ['store', 'status', 'category'],
        ExportKind::CUSTOMER => ['store', 'status', 'date_from', 'date_to'],
        ExportKind::ORDER => ['store', 'status', 'payment_status', 'date_from', 'date_to'],
    ];

    /**
     * Filter names a kind accepts.
     *
     * @param string $kind
     * @return list<string>
     */
    public static function fields(string $kind): array
    {
        return self::ALLOWED[$kind] ?? [];
    }

    /**
     * Check and normalise the filters of a kind.
     *
     * @param string $kind
     * @param array<string, mixed> $input
     * @return array<string, string>
     * @throws ExportRejected On an unknown kind or a filter that is not acceptable.
     */
    public static function check(string $kind, array $input): array
    {
        if (!ExportKind::isKnown($kind)) {
            throw new ExportRejected('error.kind');
        }
        $filter = [];
        foreach ($input as $key => $value) {
            $value = is_scalar($value) ? trim((string) $value) : '';
            if ($value === '') {
                continue;
            }
            if (!in_array($key, self::ALLOWED[$kind], true)) {
                throw new ExportRejected('error.filter', [], (string) $key);
            }
            $filter[(string) $key] = $value;
        }
        self::checkValues($kind, $filter);
        ksort($filter);

        return $filter;
    }

    /**
     * @param string $kind
     * @param array<string, string> $filter
     * @return void
     * @throws ExportRejected
     */
    private static function checkValues(string $kind, array $filter): void
    {
        if (isset($filter['store']) && !self::exists('admin_store', $filter['store'])) {
            throw new ExportRejected('error.filter', [], 'store');
        }
        if (isset($filter['status'])) {
            $ok = $kind === ExportKind::ORDER ? self::exists('shop_order_status', $filter['status']) : in_array($filter['status'], ['0', '1'], true);
            if (!$ok) {
                throw new ExportRejected('error.filter', [], 'status');
            }
        }
        if (isset($filter['payment_status']) && !self::exists('shop_payment_status', $filter['payment_status'])) {
            throw new ExportRejected('error.filter', [], 'payment_status');
        }
        if (isset($filter['category']) && !self::exists('shop_category', $filter['category'])) {
            throw new ExportRejected('error.filter', [], 'category');
        }
        foreach (['date_from', 'date_to'] as $field) {
            if (isset($filter[$field]) && !self::isDate($filter[$field])) {
                throw new ExportRejected('error.date', [], $field);
            }
        }
        if (isset($filter['date_from'], $filter['date_to']) && $filter['date_from'] > $filter['date_to']) {
            throw new ExportRejected('error.date_order', [], 'date_to');
        }
    }

    /**
     * @param string $value
     * @return bool
     */
    private static function isDate(string $value): bool
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            return false;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    /**
     * @param string $table Table without prefix.
     * @param string $id
     * @return bool
     */
    private static function exists(string $table, string $id): bool
    {
        if (mb_strlen($id) > 64) {
            return false;
        }

        return DB::connection(GP247_DB_CONNECTION)->table(GP247_DB_PREFIX . $table)->where('id', $id)->exists();
    }
}
