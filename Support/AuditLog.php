<?php

namespace App\GP247\Plugins\DataExport\Support;

use App\GP247\Plugins\DataExport\Models\ExportJob;
use GP247\Core\Models\AdminLog;

/**
 * One admin_log row per step of a run (created, ready, downloaded, cancelled, deleted): who,
 * which run, which kind of data, the filters and the counts — never a value of the file, a path
 * on disk or a signed URL.
 *
 * @aidlc-unit data-export
 * @aidlc-story US-data-export-access-control
 */
final class AuditLog
{
    /**
     * Write the audit row.
     *
     * @param string $action data_export_created | data_export_ready | data_export_downloaded | data_export_cancelled | data_export_deleted
     * @param ExportJob $job
     * @return void
     */
    public static function write(string $action, ExportJob $job): void
    {
        $request = request();
        AdminLog::create([
            'user_id' => Access::adminId(),
            'path' => Access::base(),
            'method' => 'LIVEWIRE',
            'ip' => (string) $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            'input' => json_encode([
                'action' => $action,
                'job' => $job->id,
                'kind' => $job->kind,
                'filters' => (array) $job->filters,
                'encoding' => $job->encoding,
                'phase' => $job->phase,
                'expected_rows' => (int) $job->expected_rows,
                'written_rows' => (int) $job->written_rows,
                'replaced_cells' => (int) $job->replaced_cells,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
}
