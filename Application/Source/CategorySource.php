<?php

namespace App\GP247\Plugins\DataExport\Application\Source;

/**
 * Categories: key = alias (the natural key Data Import matches), parent named by its alias,
 * name / keyword / description in every active language.
 *
 * @aidlc-unit data-export
 * @aidlc-story US-data-export-entity-csv
 * @aidlc-story US-data-export-roundtrip-format
 */
final class CategorySource extends Source
{
    private const TEXTS = ['name', 'keyword', 'description'];

    /**
     * {@inheritDoc}
     */
    public function headers(): array
    {
        return array_merge(['key', 'alias', 'parent'], $this->textHeaders(self::TEXTS), ['image', 'sort', 'status', 'top']);
    }

    /**
     * {@inheritDoc}
     */
    protected function table(): string
    {
        return 'shop_category';
    }

    /**
     * {@inheritDoc}
     */
    protected function rowsOf(array $records): array
    {
        $ids = self::ids($records);
        $texts = $this->texts('shop_category_description', 'category_id', $ids, self::TEXTS);
        $parents = $this->aliases('shop_category', array_map(static fn ($r) => (string) $r->parent, $records));
        $textHeaders = $this->textHeaders(self::TEXTS);
        $rows = [];
        foreach ($records as $r) {
            $row = [(string) $r->alias, (string) $r->alias, $parents[(string) $r->parent] ?? ''];
            foreach ($textHeaders as $column) {
                $row[] = $texts[(string) $r->id][$column] ?? '';
            }
            $rows[] = array_merge($row, [(string) $r->image, (string) $r->sort, (string) $r->status, (string) $r->top]);
        }

        return $rows;
    }
}
