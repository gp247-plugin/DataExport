<?php

namespace App\GP247\Plugins\DataExport\Application\Source;

/**
 * Brands and suppliers: key = alias, then the columns of the record as Data Import reads them.
 *
 * @aidlc-unit data-export
 * @aidlc-story US-data-export-entity-csv
 * @aidlc-story US-data-export-roundtrip-format
 */
final class NamedRecordSource extends Source
{
    /**
     * @param string $table Table without prefix (shop_brand, shop_supplier).
     * @param list<string> $columns Columns after `key`.
     */
    public function __construct(private readonly string $table, private readonly array $columns)
    {
    }

    /**
     * The brands.
     *
     * @return self
     */
    public static function brands(): self
    {
        return new self('shop_brand', ['name', 'alias', 'image', 'url', 'sort', 'status']);
    }

    /**
     * The suppliers.
     *
     * @return self
     */
    public static function suppliers(): self
    {
        return new self('shop_supplier', ['name', 'alias', 'image', 'url', 'email', 'phone', 'address', 'sort']);
    }

    /**
     * {@inheritDoc}
     */
    public function headers(): array
    {
        return array_merge(['key'], $this->columns);
    }

    /**
     * {@inheritDoc}
     */
    protected function table(): string
    {
        return $this->table;
    }

    /**
     * {@inheritDoc}
     */
    protected function rowsOf(array $records): array
    {
        $rows = [];
        foreach ($records as $r) {
            $row = [(string) $r->alias];
            foreach ($this->columns as $column) {
                $row[] = (string) ($r->{$column} ?? '');
            }
            $rows[] = $row;
        }

        return $rows;
    }
}
