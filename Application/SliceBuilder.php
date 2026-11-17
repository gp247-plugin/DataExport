<?php

namespace App\GP247\Plugins\DataExport\Application;

use App\GP247\Plugins\DataExport\Application\Source\Sources;
use App\GP247\Plugins\DataExport\Domain\ExportPhase;
use App\GP247\Plugins\DataExport\Models\ExportJob;
use GP247\Core\Backup\Application\SliceBudget;
use Illuminate\Support\Str;

/**
 * Builds the file of a run one slice at a time, within core's slice budget, so the admin screen
 * (wire:poll) finishes any size of export on a host with a 30-second limit and no cron.
 *
 * A slice takes a lease on the run (a second tab returns at once), cuts the part file back to the
 * last byte a slice confirmed (a slice killed mid-write leaves no half row), appends batches of
 * rows read after the last written key and confirms cursor + counts in one update guarded by its
 * lease — a run cancelled meanwhile is not written on. At the end the part file becomes the file.
 *
 * @aidlc-unit data-export
 * @aidlc-story US-data-export-sliced-build
 * @aidlc-adr ADR-data-export-sliced-file-build
 */
final class SliceBuilder
{
    public const RAN = 'running';
    public const DONE = 'done';
    public const IDLE = 'idle';
    public const BUSY = 'busy';
    public const FAILED = 'failed';

    /**
     * Run one slice of a run.
     *
     * @param string $jobId
     * @param float|null $budget Seconds (core's slice budget when null; 0 writes one batch).
     * @param int|null $batchRows Records per batch (config when null).
     * @return array{state: string, phase: string|null}
     */
    public function run(string $jobId, ?float $budget = null, ?int $batchRows = null): array
    {
        $budget ??= SliceBudget::seconds();
        $batchRows ??= max(1, (int) config('Plugins/DataExport.batch_rows', 500));
        $token = (string) Str::uuid();
        if (!$this->acquire($jobId, $token, $budget)) {
            $phase = ExportJob::whereKey($jobId)->value('phase');

            return ['state' => in_array($phase, ExportPhase::ACTIVE, true) ? self::BUSY : self::IDLE, 'phase' => $phase];
        }
        try {
            $job = ExportJob::findOrFail($jobId);
            $done = $this->work($job, $token, $budget, $batchRows);
            $phase = (string) ExportJob::whereKey($jobId)->value('phase');

            return ['state' => $phase === ExportPhase::CANCELLED ? self::IDLE : ($done ? self::DONE : self::RAN), 'phase' => $phase];
        } catch (\Throwable $e) {
            report($e);
            JobFiles::delete($jobId);
            ExportJob::whereKey($jobId)->where('lease_token', $token)
                ->update(['phase' => ExportPhase::FAILED, 'error' => mb_substr(class_basename($e), 0, 255)]);

            return ['state' => self::FAILED, 'phase' => ExportPhase::FAILED];
        } finally {
            ExportJob::whereKey($jobId)->where('lease_token', $token)->update(['lease_token' => null, 'lease_until' => null]);
        }
    }

    /**
     * @param string $jobId
     * @param string $token
     * @param float $budget
     * @return bool
     */
    private function acquire(string $jobId, string $token, float $budget): bool
    {
        $now = now();

        return ExportJob::whereKey($jobId)
            ->whereIn('phase', ExportPhase::ACTIVE)
            ->where(fn ($q) => $q->whereNull('lease_until')->orWhere('lease_until', '<', $now))
            ->update(['lease_token' => $token, 'lease_until' => $now->copy()->addSeconds((int) ceil(min($budget, 3600)) + 60)]) === 1;
    }

    /**
     * Write batches until the budget is spent or the data ends.
     *
     * @param ExportJob $job
     * @param string $token
     * @param float $budget
     * @param int $batchRows
     * @return bool Whether the file is complete.
     */
    private function work(ExportJob $job, string $token, float $budget, int $batchRows): bool
    {
        $source = Sources::for($job->kind);
        $encoder = new CellEncoder($job->encoding);
        if ($job->phase === ExportPhase::QUEUED) {
            $this->start($job, $token, $encoder, $source->headers());
        }
        $cursor = (array) $job->cursor;
        $handle = $this->reopen($job->id, (int) ($cursor['bytes'] ?? 0));
        $deadline = microtime(true) + $budget;
        $written = (int) $job->written_rows;
        $replaced = (int) $job->replaced_cells;
        $firstReplaced = $job->first_replaced_row;
        try {
            do {
                $batch = $source->batch((array) $job->filters, $cursor['last'] ?? null, $batchRows);
                if ($batch['last'] === null) {
                    fclose($handle);
                    $handle = null;

                    return $this->finish($job, $token);
                }
                foreach ($batch['rows'] as $row) {
                    fwrite($handle, $encoder->line($row));
                    $written++;
                    if ($encoder->replacedInLastLine() > 0) {
                        $replaced += $encoder->replacedInLastLine();
                        // Line 1 is the header, so data row n is line n + 1.
                        $firstReplaced ??= $written + 1;
                    }
                }
                fflush($handle);
                $cursor = ['last' => $batch['last'], 'bytes' => (int) ftell($handle)];
                $confirmed = ExportJob::whereKey($job->id)->where('lease_token', $token)->where('phase', ExportPhase::BUILDING)->update([
                    'cursor' => json_encode($cursor), 'written_rows' => $written, 'replaced_cells' => $replaced, 'first_replaced_row' => $firstReplaced,
                ]);
                if ($confirmed !== 1) {
                    // Cancelled while this slice wrote: the file is not wanted any more.
                    fclose($handle);
                    $handle = null;
                    JobFiles::delete($job->id);

                    return false;
                }
            } while (microtime(true) < $deadline);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        return false;
    }

    /**
     * First slice: new part file with the preamble and the header line.
     *
     * @param ExportJob $job
     * @param string $token
     * @param CellEncoder $encoder
     * @param list<string> $headers
     * @return void
     */
    private function start(ExportJob $job, string $token, CellEncoder $encoder, array $headers): void
    {
        JobFiles::ensureDir();
        $bytes = $encoder->preamble() . $encoder->line($headers);
        file_put_contents(JobFiles::partPath($job->id), $bytes, LOCK_EX);
        $cursor = ['last' => null, 'bytes' => strlen($bytes)];
        ExportJob::whereKey($job->id)->where('lease_token', $token)->update(['phase' => ExportPhase::BUILDING, 'cursor' => json_encode($cursor)]);
        $job->phase = ExportPhase::BUILDING;
        $job->cursor = $cursor;
    }

    /**
     * Open the part file for appending, cut back to the last confirmed byte.
     *
     * @param string $id
     * @param int $bytes
     * @return resource
     * @throws \RuntimeException When the part file is missing (the run cannot go on).
     */
    private function reopen(string $id, int $bytes)
    {
        $path = JobFiles::partPath($id);
        clearstatcache(true, $path);
        if (!is_file($path)) {
            throw new \RuntimeException('Part file missing.');
        }
        $handle = fopen($path, 'r+b');
        if ($handle === false) {
            throw new \RuntimeException('Part file cannot be opened.');
        }
        if (filesize($path) !== $bytes) {
            ftruncate($handle, $bytes);
        }
        fseek($handle, $bytes);

        return $handle;
    }

    /**
     * Make the part file the file of the run.
     *
     * @param ExportJob $job
     * @param string $token
     * @return bool
     */
    private function finish(ExportJob $job, string $token): bool
    {
        $part = JobFiles::partPath($job->id);
        $file = JobFiles::path($job->id);
        if (!@rename($part, $file)) {
            throw new \RuntimeException('File cannot be finished.');
        }
        clearstatcache(true, $file);
        $hours = max(1, (int) config('Plugins/DataExport.retention_hours', 24));

        $ready = ExportJob::whereKey($job->id)->where('lease_token', $token)->where('phase', ExportPhase::BUILDING)->update([
            'phase' => ExportPhase::READY, 'ready_at' => now(), 'expires_at' => now()->addHours($hours), 'file_bytes' => (int) filesize($file),
        ]) === 1;
        if (!$ready) {
            // Cancelled between the last batch and now: no file may stay behind.
            JobFiles::delete($job->id);
        }

        return $ready;
    }
}
