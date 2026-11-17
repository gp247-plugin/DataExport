<?php

namespace App\GP247\Plugins\DataExport\Application\Source;

use App\GP247\Plugins\DataExport\Domain\ExportKind;

/**
 * The source of each kind of data.
 *
 * @aidlc-unit data-export
 * @aidlc-story US-data-export-entity-csv
 */
final class Sources
{
    /**
     * Source of a kind.
     *
     * @param string $kind
     * @return Source
     * @throws \InvalidArgumentException On an unknown kind.
     */
    public static function for(string $kind): Source
    {
        return match ($kind) {
            ExportKind::CATEGORY => new CategorySource,
            ExportKind::BRAND => NamedRecordSource::brands(),
            ExportKind::SUPPLIER => NamedRecordSource::suppliers(),
            ExportKind::PRODUCT => new ProductSource,
            ExportKind::CUSTOMER => new CustomerSource,
            ExportKind::ORDER => new OrderSource,
            default => throw new \InvalidArgumentException('Unknown kind of data.'),
        };
    }
}
