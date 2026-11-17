<?php

namespace App\GP247\Plugins\DataExport\Application;

use App\GP247\Plugins\DataExport\Application\Source\Sources;
use App\GP247\Plugins\DataExport\Domain\ExportKind;
use App\GP247\Plugins\DataExport\Domain\ExportPhase;
use App\GP247\Plugins\DataExport\Domain\TargetEncoding;
use App\GP247\Plugins\DataExport\Models\ExportJob;
use Illuminate\Support\Facades\URL;

/**
 * What a user can do with export runs: estimate the rows of a filter, create a run, cancel it,
 * delete its file early and get a short-lived download link. Authorization is the caller's job
 * (the admin screen and the download route check permission, root store context and owner).
 *
 * @aidlc-unit data-export
 * @aidlc-story US-data-export-entity-csv
 * @aidlc-story US-data-export-filters
 * @aidlc-story US-data-export-download-retention
 */
final class ExportService
{
    /**
     * Number of file rows a filter gives.
     *
     * @param string $kind
     * @param array<string, mixed> $input Raw filters from the screen.
     * @return int
     * @throws ExportRejected On a bad kind or filter.
     */
    public function estimate(string $kind, array $input): int
    {
        $filter = ExportFilter::check($kind, $input);

        return Sources::for($kind)->count($filter);
    }

    /**
     * Create a run (queued) after checking the encoding, the filters, the admin's running runs
     * and that the filter gives at least one row.
     *
     * @param string $kind
     * @param array<string, mixed> $input Raw filters.
     * @param string $encoding
     * @param string $adminId
     * @return ExportJob
     * @throws ExportRejected
     */
    public function create(string $kind, array $input, string $encoding, string $adminId): ExportJob
    {
        $filter = ExportFilter::check($kind, $input);
        if (!in_array($encoding, TargetEncoding::available(), true)) {
            throw new ExportRejected('error.encoding', [], 'encoding');
        }
        (new Retention)->sweep();
        $running = ExportJob::where('admin_id', $adminId)->whereIn('phase', ExportPhase::ACTIVE)->count();
        if ($running >= max(1, (int) config('Plugins/DataExport.max_building_per_admin', 1))) {
            throw new ExportRejected('error.busy');
        }
        $rows = Sources::for($kind)->count($filter);
        if ($rows === 0) {
            throw new ExportRejected('error.no_rows');
        }

        return ExportJob::create([
            'id' => ExportJob::newId(),
            'admin_id' => $adminId,
            'kind' => $kind,
            'filters' => $filter,
            'encoding' => $encoding,
            'phase' => ExportPhase::QUEUED,
            'expected_rows' => $rows,
        ]);
    }

    /**
     * Stop a run that is waiting or being built; its part file goes.
     *
     * @param ExportJob $job
     * @return bool Whether the run was cancelled.
     */
    public function cancel(ExportJob $job): bool
    {
        $done = ExportJob::whereKey($job->id)->whereIn('phase', ExportPhase::ACTIVE)
            ->update(['phase' => ExportPhase::CANCELLED, 'lease_token' => null, 'lease_until' => null]) === 1;
        if ($done) {
            JobFiles::delete($job->id);
        }

        return $done;
    }

    /**
     * Delete the file of a ready run before it expires.
     *
     * @param ExportJob $job
     * @return bool Whether the file was deleted.
     */
    public function delete(ExportJob $job): bool
    {
        $done = ExportJob::whereKey($job->id)->where('phase', ExportPhase::READY)->update(['phase' => ExportPhase::DELETED]) === 1;
        if ($done) {
            JobFiles::delete($job->id);
        }

        return $done;
    }

    /**
     * Signed download link, valid for a few minutes.
     *
     * @param ExportJob $job
     * @return string
     */
    public function downloadUrl(ExportJob $job): string
    {
        return URL::temporarySignedRoute(
            'admin_data_export.download',
            now()->addMinutes(max(1, (int) config('Plugins/DataExport.link_minutes', 5))),
            ['group' => ExportKind::group($job->kind), 'id' => $job->id]
        );
    }

    /**
     * File name shown to the user: kind, store when filtered, date and time of the run.
     *
     * @param ExportJob $job
     * @return string
     */
    public static function displayName(ExportJob $job): string
    {
        $store = (string) (((array) $job->filters)['store'] ?? '');
        $store = $store === '' ? '' : '-' . preg_replace('/[^A-Za-z0-9_-]/', '', $store);

        return $job->kind . $store . '-' . $job->created_at?->format('Ymd-His') . '.csv';
    }
}
