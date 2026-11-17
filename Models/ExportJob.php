<?php

namespace App\GP247\Plugins\DataExport\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One export run: kind, checked filters, encoding, phase, the cursor of the build, counts and
 * the dates of the file. Kind, filters, encoding and admin never change after creation.
 *
 * @property string $id
 * @property string $admin_id
 * @property string $kind
 * @property array|null $filters
 * @property string $encoding
 * @property string $phase
 * @property int $expected_rows
 * @property int $written_rows
 * @property int $replaced_cells
 * @property int|null $first_replaced_row
 * @property array|null $cursor
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $ready_at
 * @property \Illuminate\Support\Carbon|null $downloaded_at
 *
 * @aidlc-unit data-export
 * @aidlc-story US-data-export-sliced-build
 */
class ExportJob extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $connection = GP247_DB_CONNECTION;
    protected $table = GP247_DB_PREFIX . ExtensionModel::TABLE_JOB;

    protected $fillable = [
        'id', 'admin_id', 'kind', 'filters', 'encoding', 'phase', 'expected_rows', 'written_rows', 'replaced_cells', 'first_replaced_row',
        'cursor', 'lease_token', 'lease_until', 'error', 'file_bytes', 'ready_at', 'expires_at', 'downloaded_at',
    ];

    protected $casts = [
        'filters' => 'array', 'cursor' => 'array', 'lease_until' => 'datetime', 'ready_at' => 'datetime', 'expires_at' => 'datetime',
        'downloaded_at' => 'datetime', 'expected_rows' => 'integer', 'written_rows' => 'integer', 'replaced_cells' => 'integer',
        'first_replaced_row' => 'integer', 'file_bytes' => 'integer',
    ];

    /**
     * A new run id (lower-case ULID: sortable by creation, safe in a file name).
     *
     * @return string
     */
    public static function newId(): string
    {
        return strtolower((string) Str::ulid());
    }

    /**
     * Whether the file of the run can be downloaded now.
     *
     * @return bool
     */
    public function isDownloadable(): bool
    {
        return $this->phase === \App\GP247\Plugins\DataExport\Domain\ExportPhase::READY
            && $this->expires_at !== null && $this->expires_at->isFuture();
    }
}
