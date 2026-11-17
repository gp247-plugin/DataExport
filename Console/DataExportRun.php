<?php

namespace App\GP247\Plugins\DataExport\Console;

use App\GP247\Plugins\DataExport\Application\SliceBuilder;
use App\GP247\Plugins\DataExport\Models\ExportJob;
use Illuminate\Console\Command;

/**
 * Builds a run created on the admin screen to the end in one go (VPS shortcut; the screen does
 * the same slice by slice on any host). Runs are always created on the screen, so a file always
 * belongs to an admin who may download it.
 *
 * @aidlc-unit data-export
 * @aidlc-story US-data-export-sliced-build
 */
class DataExportRun extends Command
{
    protected $signature = 'data-export:run {id : Id of a run created on the Data Export screen}';

    protected $description = 'Build the file of a Data Export run to the end';

    /**
     * Run the slices until the run is no longer being built.
     *
     * @return int
     */
    public function handle(): int
    {
        $id = (string) $this->argument('id');
        if (!preg_match('/^[0-9a-z]{26}$/', $id) || !ExportJob::whereKey($id)->exists()) {
            $this->error('No such run.');

            return self::FAILURE;
        }
        $builder = new SliceBuilder;
        do {
            $result = $builder->run($id, 3600.0);
        } while ($result['state'] === SliceBuilder::RAN);

        $job = ExportJob::findOrFail($id);
        $this->line(sprintf('%s: %s, %d / %d rows', $job->id, $job->phase, $job->written_rows, $job->expected_rows));

        return $result['state'] === SliceBuilder::FAILED ? self::FAILURE : self::SUCCESS;
    }
}
