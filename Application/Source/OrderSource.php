<?php

namespace App\GP247\Plugins\DataExport\Application\Source;

/**
 * Orders: one row per product line, the order columns on the first row of the order only (the
 * layout Data Import groups by `key`); statuses by name, the buyer's account by e-mail.
 *
 * @aidlc-unit data-export
 * @aidlc-story US-data-export-entity-csv
 * @aidlc-story US-data-export-roundtrip-format
 */
final class OrderSource extends Source
{
    /** Order columns read as they are stored. */
    private const ORDER_COLUMNS = [
        'email', 'first_name', 'last_name', 'phone', 'company', 'address1', 'address2', 'address3', 'city', 'district', 'ward', 'country',
        'postcode', 'currency', 'exchange_rate', 'payment_method', 'shipping_method', 'comment',
    ];

    private const MONEY = ['subtotal', 'shipping', 'tax', 'discount', 'other_fee', 'total', 'received'];

    /** Line column => order detail column. */
    private const LINE = [
        'line_sku' => 'sku', 'line_name' => 'name', 'line_price' => 'price', 'line_qty' => 'qty', 'line_total' => 'total_price',
        'line_tax' => 'tax', 'line_tax_rate' => 'tax_rate', 'line_discount' => 'discount',
    ];

    /** Line columns that hold numbers. */
    private const LINE_NUMBERS = ['line_price', 'line_qty', 'line_total', 'line_tax', 'line_tax_rate', 'line_discount'];

    /** @var array<string, array<string, string>>|null Status kind => id => name. */
    private ?array $statusNames = null;

    /**
     * {@inheritDoc}
     */
    public function headers(): array
    {
        return array_merge(['key', 'customer'], self::ORDER_COLUMNS, self::MONEY, ['status', 'shipping_status', 'payment_status', 'created_at'], array_keys(self::LINE));
    }

    /**
     * {@inheritDoc}
     */
    protected function table(): string
    {
        return 'shop_order';
    }

    /**
     * File rows = product lines of the orders (an order without a line still gives one row).
     *
     * @param array<string, string> $filter
     * @return int
     */
    public function count(array $filter): int
    {
        return (int) $this->filtered($filter)->leftJoin(GP247_DB_PREFIX . 'shop_order_detail as d', 'd.order_id', '=', 't.id')->count();
    }

    /**
     * {@inheritDoc}
     */
    protected function filtered(array $filter): \Illuminate\Database\Query\Builder
    {
        $query = parent::filtered($filter);
        if (isset($filter['payment_status'])) {
            $query->where('t.payment_status', $filter['payment_status']);
        }

        return $query;
    }

    /**
     * {@inheritDoc}
     */
    protected function rowsOf(array $records): array
    {
        $ids = self::ids($records);
        $lines = [];
        foreach ($this->db()->table(GP247_DB_PREFIX . 'shop_order_detail')->whereIn('order_id', $ids)->orderBy('id')->get() as $line) {
            $lines[(string) $line->order_id][] = $line;
        }
        $customerIds = array_values(array_filter(array_map(static fn ($r) => (string) $r->customer_id, $records), static fn ($id) => $id !== '' && $id !== '0'));
        $emails = $customerIds === [] ? [] : $this->db()->table(GP247_DB_PREFIX . 'shop_customer')->whereIn('id', $customerIds)->pluck('email', 'id')->all();
        $orderWidth = count($this->headers()) - count(self::LINE);

        $rows = [];
        foreach ($records as $r) {
            $head = $this->orderCells($r, (string) ($emails[(string) $r->customer_id] ?? ''));
            $blank = array_merge([(string) $r->id], array_fill(0, $orderWidth - 1, ''));
            foreach ($lines[(string) $r->id] ?? [null] as $i => $line) {
                $rows[] = array_merge($i === 0 ? $head : $blank, $this->lineCells($line));
            }
        }

        return $rows;
    }

    /**
     * Order columns of the first row.
     *
     * @param object $r
     * @param string $customerEmail
     * @return list<string>
     */
    private function orderCells(object $r, string $customerEmail): array
    {
        $cells = [(string) $r->id, $customerEmail];
        foreach (self::ORDER_COLUMNS as $column) {
            $cells[] = $column === 'exchange_rate' ? self::number($r->{$column}) : (string) ($r->{$column} ?? '');
        }
        foreach (self::MONEY as $column) {
            $cells[] = self::number($r->{$column});
        }
        foreach (['status' => 'shop_order_status', 'shipping_status' => 'shop_shipping_status', 'payment_status' => 'shop_payment_status'] as $column => $table) {
            $cells[] = $this->statusName($table, (string) $r->{$column});
        }
        $cells[] = (string) $r->created_at;

        return $cells;
    }

    /**
     * Line columns of a row (empty for an order without lines).
     *
     * @param object|null $line
     * @return list<string>
     */
    private function lineCells(?object $line): array
    {
        $cells = [];
        foreach (self::LINE as $column => $field) {
            $value = $line?->{$field};
            $cells[] = in_array($column, self::LINE_NUMBERS, true) ? self::number($value) : (string) ($value ?? '');
        }

        return $cells;
    }

    /**
     * Name of a status (the id when the status no longer exists).
     *
     * @param string $table
     * @param string $id
     * @return string
     */
    private function statusName(string $table, string $id): string
    {
        $this->statusNames[$table] ??= $this->db()->table(GP247_DB_PREFIX . $table)->pluck('name', 'id')->map(static fn ($n) => (string) $n)->all();

        return $id === '' ? '' : ($this->statusNames[$table][$id] ?? $id);
    }
}
