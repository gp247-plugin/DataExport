<?php

namespace App\GP247\Plugins\DataExport\Application;

use App\GP247\Plugins\DataExport\Domain\ExportPhase;
use App\GP247\Plugins\DataExport\Models\ExportJob;

/**
 * Lazy clean-up (no cron — run when the screen opens and when a run is created): files past their
 * expiry, runs stuck while building, old finished runs, and files no run owns. Each pass handles
 * a bounded number of runs so it never makes a request slow.
 *
 * @aidlc-unit data-export
 * @aidlc-story US-data-export-download-retention
 */
final class Retention
{
    private const PER_PASS = 50;

    /**
     * One clean-up pass.
     *
     * @return int Runs and files handled.
     */
    public function sweep(): int
    {
        $handled = 0;
        foreach (ExportJob::where('phase', ExportPhase::READY)->where('expires_at', '<', now())->limit(self::PER_PASS)->pluck('id') as $id) {
            if (ExportJob::whereKey($id)->where('phase', ExportPhase::READY)->update(['phase' => ExportPhase::EXPIRED]) === 1) {
                JobFiles::delete((string) $id);
                $handled++;
            }
        }
        $idle = now()->subMinutes(max(1, (int) config('Plugins/DataExport.idle_minutes', 15)));
        foreach (ExportJob::whereIn('phase', ExportPhase::ACTIVE)->where('updated_at', '<', $idle)
            ->where(fn ($q) => $q->whereNull('lease_until')->orWhere('lease_until', '<', now()))->limit(self::PER_PASS)->pluck('id') as $id) {
            if (ExportJob::whereKey($id)->whereIn('phase', ExportPhase::ACTIVE)->update(['phase' => ExportPhase::FAILED, 'error' => 'stalled']) === 1) {
                JobFiles::delete((string) $id);
                $handled++;
            }
        }
        $handled += ExportJob::whereIn('phase', ExportPhase::FINAL)
            ->where('updated_at', '<', now()->subDays(max(1, (int) config('Plugins/DataExport.log_days', 90))))->limit(self::PER_PASS)->delete();

        return $handled + $this->orphans($idle->getTimestamp());
    }

    /**
     * Remove files no waiting, building or ready run owns, once they are older than $before
     * (a file being created right now is never touched).
     *
     * @param int $before Unix time.
     * @return int
     */
    private function orphans(int $before): int
    {
        $ids = JobFiles::idsOnDisk();
        if ($ids === []) {
            return 0;
        }
        $owned = ExportJob::whereIn('id', $ids)->whereIn('phase', array_merge(ExportPhase::ACTIVE, [ExportPhase::READY]))->pluck('id')->all();
        $removed = 0;
        foreach (array_slice(array_diff($ids, $owned), 0, self::PER_PASS) as $id) {
            $old = true;
            foreach ([JobFiles::path($id), JobFiles::partPath($id)] as $file) {
                if (is_file($file) && (int) filemtime($file) >= $before) {
                    $old = false;
                }
            }
            if ($old) {
                JobFiles::delete($id);
                $removed++;
            }
        }

        return $removed;
    }
}
