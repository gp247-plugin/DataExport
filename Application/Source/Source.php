<?php

namespace App\GP247\Plugins\DataExport\Application\Source;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Reads one kind of shop data for an export: the column header, the number of rows a filter
 * gives, and batches of rows after a key (keyset on the primary key — no OFFSET, so a slice can
 * resume exactly where the previous one stopped). Relations of a batch are loaded in one query
 * per relation, so memory depends on the batch size, never on the table size.
 *
 * @aidlc-unit data-export
 * @aidlc-story US-data-export-entity-csv
 * @aidlc-story US-data-export-sliced-build
 */
abstract class Source
{
    /** @var list<string>|null */
    private ?array $languages = null;

    /**
     * Column names of the file, in order.
     *
     * @return list<string>
     */
    abstract public function headers(): array;

    /**
     * Main table without prefix.
     *
     * @return string
     */
    abstract protected function table(): string;

    /**
     * File rows of a batch of records (records ordered by id).
     *
     * @param list<object> $records
     * @return list<list<string>>
     */
    abstract protected function rowsOf(array $records): array;

    /**
     * Number of file rows the filter gives.
     *
     * @param array<string, string> $filter Checked by ExportFilter.
     * @return int
     */
    public function count(array $filter): int
    {
        return $this->filtered($filter)->count();
    }

    /**
     * The next records after $after, as file rows, and the id of the last record read.
     *
     * @param array<string, string> $filter
     * @param string|null $after Id of the last record already written.
     * @param int $limit Records per batch.
     * @return array{rows: list<list<string>>, last: string|null}
     */
    public function batch(array $filter, ?string $after, int $limit): array
    {
        $records = $this->filtered($filter)
            ->when($after !== null, fn (Builder $q) => $q->where('t.id', '>', $after))
            ->orderBy('t.id')
            ->limit(max(1, $limit))
            ->get($this->columns())
            ->all();
        if ($records === []) {
            return ['rows' => [], 'last' => null];
        }

        return ['rows' => $this->rowsOf($records), 'last' => (string) end($records)->id];
    }

    /**
     * Columns of the main table read into a batch.
     *
     * @return list<string>
     */
    protected function columns(): array
    {
        return ['t.*'];
    }

    /**
     * Records of the main table matching the filter (alias `t`).
     *
     * @param array<string, string> $filter
     * @return Builder
     */
    protected function filtered(array $filter): Builder
    {
        $query = $this->db()->table(GP247_DB_PREFIX . $this->table() . ' as t');
        if (isset($filter['store'])) {
            $query->where('t.store_id', $filter['store']);
        }
        if (isset($filter['status'])) {
            $query->where('t.status', $filter['status']);
        }
        if (isset($filter['date_from'])) {
            $query->where('t.created_at', '>=', $filter['date_from'] . ' 00:00:00');
        }
        if (isset($filter['date_to'])) {
            $query->where('t.created_at', '<=', $filter['date_to'] . ' 23:59:59');
        }

        return $query;
    }

    /**
     * Database connection of the shop.
     *
     * @return Connection
     */
    protected function db(): Connection
    {
        return DB::connection(GP247_DB_CONNECTION);
    }

    /**
     * Active language codes, the default first (the order Data Import uses).
     *
     * @return list<string>
     */
    protected function languages(): array
    {
        return $this->languages ??= array_map('strval', array_keys(\GP247\Core\Models\AdminLanguage::getListActive()->all()));
    }

    /**
     * `field:<lang>` columns of the text fields, for every active language.
     *
     * @param list<string> $fields
     * @return list<string>
     */
    protected function textHeaders(array $fields): array
    {
        $headers = [];
        foreach ($fields as $field) {
            foreach ($this->languages() as $lang) {
                $headers[] = $field . ':' . $lang;
            }
        }

        return $headers;
    }

    /**
     * Texts of the records by id: id => ["field:lang" => text].
     *
     * @param string $table Description table without prefix.
     * @param string $foreignKey
     * @param list<string> $ids
     * @param list<string> $fields
     * @return array<string, array<string, string>>
     */
    protected function texts(string $table, string $foreignKey, array $ids, array $fields): array
    {
        $out = [];
        $rows = $this->db()->table(GP247_DB_PREFIX . $table)->whereIn($foreignKey, $ids)->whereIn('lang', $this->languages())->get();
        foreach ($rows as $row) {
            foreach ($fields as $field) {
                $out[(string) $row->{$foreignKey}][$field . ':' . $row->lang] = (string) ($row->{$field} ?? '');
            }
        }

        return $out;
    }

    /**
     * Aliases of records of a table by id.
     *
     * @param string $table Without prefix.
     * @param list<string> $ids
     * @return array<string, string>
     */
    protected function aliases(string $table, array $ids): array
    {
        $ids = array_values(array_filter(array_unique($ids), static fn ($id) => $id !== '' && $id !== '0'));
        if ($ids === []) {
            return [];
        }

        return $this->db()->table(GP247_DB_PREFIX . $table)->whereIn('id', $ids)->pluck('alias', 'id')->map(static fn ($a) => (string) $a)->all();
    }

    /**
     * A stored number as text, without trailing zeros (as Data Import compares numbers).
     *
     * @param mixed $value
     * @return string
     */
    protected static function number($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $text = (string) $value;

        return str_contains($text, '.') ? rtrim(rtrim($text, '0'), '.') : $text;
    }

    /**
     * Ids of the records.
     *
     * @param list<object> $records
     * @return list<string>
     */
    protected static function ids(array $records): array
    {
        return array_map(static fn ($r) => (string) $r->id, $records);
    }
}
