<?php

namespace App\GP247\Plugins\DataExport\Admin\Livewire;

use App\GP247\Plugins\DataExport\Application\ExportFilter;
use App\GP247\Plugins\DataExport\Domain\ExportKind;
use Illuminate\Support\Facades\DB;

/**
 * Choices the filter form offers for a kind: which filters it has, the order and payment
 * statuses, the stores (only when the site has more than one) and the categories of products.
 *
 * @aidlc-unit data-export
 * @aidlc-story US-data-export-filters
 */
final class FilterOptions
{
    /** Most categories listed in the product filter. */
    private const MAX_CATEGORIES = 500;

    /**
     * Options of the form for a kind.
     *
     * @param string $kind
     * @return array{fields: list<string>, statuses: array<string, string>, payment: array<string, string>, stores: array<string, string>, categories: array<string, string>}
     */
    public static function for(string $kind): array
    {
        $fields = ExportKind::isKnown($kind) ? ExportFilter::fields($kind) : [];
        $db = DB::connection(GP247_DB_CONNECTION);
        $p = GP247_DB_PREFIX;
        $stores = $db->table($p . 'admin_store')->orderBy('id')->limit(200)->get(['id', 'code', 'domain']);

        return [
            'fields' => $fields,
            'statuses' => $kind === ExportKind::ORDER ? self::names($p . 'shop_order_status') : [],
            'payment' => in_array('payment_status', $fields, true) ? self::names($p . 'shop_payment_status') : [],
            'stores' => $stores->count() > 1 ? $stores->mapWithKeys(static fn ($s) => [(string) $s->id => trim((string) ($s->code ?: $s->domain ?: $s->id))])->all() : [],
            'categories' => in_array('category', $fields, true) ? $db->table($p . 'shop_category')->orderBy('alias')->limit(self::MAX_CATEGORIES)
                ->pluck('alias', 'id')->map(static fn ($a) => (string) $a)->all() : [],
        ];
    }

    /**
     * id => name of a status table.
     *
     * @param string $table
     * @return array<string, string>
     */
    private static function names(string $table): array
    {
        return DB::connection(GP247_DB_CONNECTION)->table($table)->orderBy('id')->pluck('name', 'id')->map(static fn ($n) => (string) $n)->all();
    }
}
