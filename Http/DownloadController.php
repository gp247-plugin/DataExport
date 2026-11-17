<?php

namespace App\GP247\Plugins\DataExport\Http;

use App\GP247\Plugins\DataExport\Application\ExportService;
use App\GP247\Plugins\DataExport\Application\JobFiles;
use App\GP247\Plugins\DataExport\Domain\ExportKind;
use App\GP247\Plugins\DataExport\Domain\TargetEncoding;
use App\GP247\Plugins\DataExport\Models\ExportJob;
use App\GP247\Plugins\DataExport\Support\Access;
use App\GP247\Plugins\DataExport\Support\AuditLog;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the file of a ready run to the admin who made it. Reached only through a signed link
 * (ValidateSignature) and the admin middleware (permission of the group in the path); here the
 * run must be the caller's own, of that group, ready and not expired, and the file must sit in
 * the plugin's folder. Every refusal gives no hint of where the file is.
 *
 * @aidlc-unit data-export
 * @aidlc-story US-data-export-download-retention
 * @aidlc-adr ADR-data-export-download-protection
 */
final class DownloadController
{
    /**
     * Send the file.
     *
     * @param string $group Permission group named in the path.
     * @param string $id Run id.
     * @return BinaryFileResponse
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 403 / 404 / 410.
     */
    public function __invoke(string $group, string $id): BinaryFileResponse
    {
        if (!Access::isRoot() || !Access::allows($group)) {
            abort(403);
        }
        $job = ExportJob::whereKey($id)->first();
        if ($job === null || $job->admin_id !== Access::adminId() || ExportKind::group($job->kind) !== $group) {
            abort(403);
        }
        if (!$job->isDownloadable()) {
            abort(410);
        }
        $path = JobFiles::path($job->id);
        $real = realpath($path);
        $dir = realpath(JobFiles::dir());
        if ($real === false || $dir === false || !str_starts_with($real, $dir . DIRECTORY_SEPARATOR)) {
            abort(410);
        }

        $job->forceFill(['downloaded_at' => now()])->save();
        AuditLog::write('data_export_downloaded', $job);

        return response()->download($real, ExportService::displayName($job), [
            'Content-Type' => 'text/csv; charset=' . TargetEncoding::charset($job->encoding),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
