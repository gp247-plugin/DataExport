<?php

namespace App\GP247\Plugins\DataExport\Application\Source;

/**
 * Customers: contact and address columns only. The password hash, remember token and every
 * other credential are never selected into a row (the column list is an allow-list).
 *
 * @aidlc-unit data-export
 * @aidlc-story US-data-export-entity-csv
 * @aidlc-story US-data-export-download-retention
 */
final class CustomerSource extends Source
{
    /** Columns of the file, in order; also the only columns read into a row. */
    private const COLUMNS = [
        'email', 'first_name', 'last_name', 'first_name_kana', 'last_name_kana', 'phone', 'company', 'sex', 'birthday', 'group',
        'country', 'postcode', 'city', 'district', 'ward', 'address1', 'address2', 'address3', 'status',
    ];

    /**
     * {@inheritDoc}
     */
    public function headers(): array
    {
        return self::COLUMNS;
    }

    /**
     * Only the exported columns (and the key) are read — the password hash never leaves the database.
     *
     * @return list<string>
     */
    protected function columns(): array
    {
        return array_merge(['t.id'], array_map(static fn ($c) => 't.' . $c, self::COLUMNS));
    }

    /**
     * {@inheritDoc}
     */
    protected function table(): string
    {
        return 'shop_customer';
    }

    /**
     * {@inheritDoc}
     */
    protected function rowsOf(array $records): array
    {
        $rows = [];
        foreach ($records as $r) {
            $row = [];
            foreach (self::COLUMNS as $column) {
                $row[] = (string) ($r->{$column} ?? '');
            }
            $rows[] = $row;
        }

        return $rows;
    }
}
