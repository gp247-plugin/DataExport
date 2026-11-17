<?php

namespace App\GP247\Plugins\DataExport\Application\Source;

use Illuminate\Database\Query\Builder;

/**
 * Single products (the kind Data Import writes): SKU, texts in every active language, prices and
 * stock, tags, categories / brand / supplier by alias, and every image as an absolute URL (main
 * image first) so Data Import on another site downloads them.
 *
 * @aidlc-unit data-export
 * @aidlc-story US-data-export-entity-csv
 * @aidlc-story US-data-export-roundtrip-format
 */
final class ProductSource extends Source
{
    private const TEXTS = ['name', 'keyword', 'description', 'content'];

    private const SCALARS = ['price', 'cost', 'stock', 'minimum', 'weight'];

    /** Separator of several values in one cell (one of those Data Import splits on). */
    public const LIST_SEPARATOR = '|';

    /**
     * {@inheritDoc}
     */
    public function headers(): array
    {
        return array_merge(['sku', 'alias'], $this->textHeaders(self::TEXTS), self::SCALARS,
            ['status', 'sort', 'tags', 'categories', 'brand', 'supplier', 'images']);
    }

    /**
     * {@inheritDoc}
     */
    protected function table(): string
    {
        return 'shop_product';
    }

    /**
     * {@inheritDoc}
     */
    protected function filtered(array $filter): Builder
    {
        $query = parent::filtered($filter)->where('t.kind', defined('GP247_PRODUCT_SINGLE') ? GP247_PRODUCT_SINGLE : 0);
        if (isset($filter['category'])) {
            $query->whereExists(fn (Builder $q) => $q->selectRaw('1')->from(GP247_DB_PREFIX . 'shop_product_category as pc')
                ->whereColumn('pc.product_id', 't.id')->where('pc.category_id', $filter['category']));
        }

        return $query;
    }

    /**
     * {@inheritDoc}
     */
    protected function rowsOf(array $records): array
    {
        $ids = self::ids($records);
        $texts = $this->texts('shop_product_description', 'product_id', $ids, self::TEXTS);
        $categories = $this->grouped($this->db()->table(GP247_DB_PREFIX . 'shop_product_category as pc')
            ->join(GP247_DB_PREFIX . 'shop_category as c', 'c.id', '=', 'pc.category_id')
            ->whereIn('pc.product_id', $ids)->orderBy('c.alias')->get(['pc.product_id as owner', 'c.alias as value']));
        $tags = $this->grouped($this->db()->table(GP247_DB_PREFIX . 'shop_product_tag_pivot as tp')
            ->join(GP247_DB_PREFIX . 'shop_product_tag as g', 'g.id', '=', 'tp.tag_id')
            ->whereIn('tp.product_id', $ids)->get(['tp.product_id as owner', 'g.name as value']));
        $images = $this->grouped($this->db()->table(GP247_DB_PREFIX . 'shop_product_image')
            ->whereIn('product_id', $ids)->orderBy('id')->get(['product_id as owner', 'image as value']));
        $brands = $this->aliases('shop_brand', array_map(static fn ($r) => (string) $r->brand_id, $records));
        $suppliers = $this->aliases('shop_supplier', array_map(static fn ($r) => (string) $r->supplier_id, $records));
        $textHeaders = $this->textHeaders(self::TEXTS);

        $rows = [];
        foreach ($records as $r) {
            $id = (string) $r->id;
            $row = [(string) $r->sku, (string) $r->alias];
            foreach ($textHeaders as $column) {
                $row[] = $texts[$id][$column] ?? '';
            }
            foreach (self::SCALARS as $field) {
                $row[] = self::number($r->{$field});
            }
            $pictures = array_filter(array_merge([(string) $r->image], $images[$id] ?? []), 'strlen');
            $rows[] = array_merge($row, [
                (string) $r->status, (string) $r->sort, implode(', ', $tags[$id] ?? []), implode(self::LIST_SEPARATOR, $categories[$id] ?? []),
                $brands[(string) $r->brand_id] ?? '', $suppliers[(string) $r->supplier_id] ?? '',
                implode(self::LIST_SEPARATOR, array_map([self::class, 'absoluteUrl'], array_values($pictures))),
            ]);
        }

        return $rows;
    }

    /**
     * A stored image path as an absolute URL (a URL stays as it is).
     *
     * @param string $path
     * @return string
     */
    public static function absoluteUrl(string $path): string
    {
        return preg_match('#^https?://#i', $path) ? $path : url('/' . ltrim($path, '/'));
    }

    /**
     * owner => list of values.
     *
     * @param iterable<object> $rows Rows with `owner` and `value`.
     * @return array<string, list<string>>
     */
    private function grouped(iterable $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->owner][] = (string) $row->value;
        }

        return $out;
    }
}
