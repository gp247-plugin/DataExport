<?php

namespace App\GP247\Plugins\DataExport\Admin\Livewire;

use App\GP247\Plugins\DataExport\Application\ExportFilter;
use App\GP247\Plugins\DataExport\Application\ExportRejected;
use App\GP247\Plugins\DataExport\Application\ExportService;
use App\GP247\Plugins\DataExport\Application\Retention;
use App\GP247\Plugins\DataExport\Application\SliceBuilder;
use App\GP247\Plugins\DataExport\Domain\ExportKind;
use App\GP247\Plugins\DataExport\Domain\ExportPhase;
use App\GP247\Plugins\DataExport\Domain\TargetEncoding;
use App\GP247\Plugins\DataExport\Models\ExportJob;
use App\GP247\Plugins\DataExport\Support\Access;
use App\GP247\Plugins\DataExport\Support\AuditLog;
use GP247\Core\AdminShell\Infrastructure\GP247AdminComponent;
use Livewire\WithPagination;

/**
 * The Data Export screen: choose a kind of data, its filters and the encoding, see how many rows
 * the file will have, create the file, follow it being built (wire:poll pulls one slice every
 * 2 s) and download, cancel or delete it. Only kinds of granted groups are offered; every action
 * is re-authorized on the server, only at the root store context, and only on the admin's own runs.
 *
 * @aidlc-unit data-export
 * @aidlc-story US-data-export-entity-csv
 * @aidlc-story US-data-export-filters
 * @aidlc-story US-data-export-sliced-build
 * @aidlc-story US-data-export-access-control
 */
class ExportIndex extends GP247AdminComponent
{
    use WithPagination;

    protected ?string $permission = 'admin_data-export';

    public string $kind = '';

    public string $store = '';

    public string $status = '';

    public string $paymentStatus = '';

    public string $category = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $encoding = TargetEncoding::UTF8_BOM;

    public ?int $estimate = null;

    /**
     * Open the screen (root store context only) and clean up lazily.
     *
     * @return void
     */
    public function mount(): void
    {
        Access::assertRoot();
        parent::mount();
        $this->kind = Access::exportableKinds()[0] ?? '';
        (new Retention)->sweep();
    }

    /**
     * Any change of the form makes the last estimate stale.
     *
     * @return void
     */
    public function updated(): void
    {
        $this->estimate = null;
        $this->resetErrorBag();
    }

    /**
     * Count the rows the file will have.
     *
     * @return void
     */
    public function estimateRows(): void
    {
        $this->authorizeKind('estimateRows');
        try {
            $this->estimate = (new ExportService)->estimate($this->kind, $this->filterInput());
        } catch (ExportRejected $e) {
            $this->addError($e->field !== '' ? $e->field : 'kind', $e->getMessage());
        }
    }

    /**
     * Create the run and build its first slice right away (a small file is ready at once).
     *
     * @return void
     */
    public function create(): void
    {
        $this->authorizeKind('create');
        try {
            $job = (new ExportService)->create($this->kind, $this->filterInput(), $this->encoding, Access::adminId());
        } catch (ExportRejected $e) {
            $this->addError($e->field !== '' ? $e->field : 'kind', $e->getMessage());

            return;
        }
        AuditLog::write('data_export_created', $job);
        $this->buildSlice($job);
        $this->estimate = null;
        $this->resetPage();
    }

    /**
     * Pull one slice of each of the admin's runs being built (wire:poll).
     *
     * @return void
     */
    public function dataExportTick(): void
    {
        $jobs = ExportJob::where('admin_id', Access::adminId())->whereIn('phase', ExportPhase::ACTIVE)->orderBy('id')->limit(3)->get();
        foreach ($jobs as $job) {
            Access::authorize(ExportKind::group($job->kind), $job->id, 'dataExportTick');
            $this->buildSlice($job);
        }
    }

    /**
     * Stop a run being built.
     *
     * @param string $id
     * @return void
     */
    public function cancel(string $id): void
    {
        $job = Access::ownJob($id);
        Access::authorize(ExportKind::group($job->kind), $job->id, 'cancel');
        if ((new ExportService)->cancel($job)) {
            AuditLog::write('data_export_cancelled', $job->refresh());
        }
    }

    /**
     * Delete the file of a ready run now.
     *
     * @param string $id
     * @return void
     */
    public function deleteFile(string $id): void
    {
        $job = Access::ownJob($id);
        Access::authorize(ExportKind::group($job->kind), $job->id, 'deleteFile');
        if ((new ExportService)->delete($job)) {
            AuditLog::write('data_export_deleted', $job->refresh());
        }
    }

    /**
     * Send the browser to a fresh signed link of the file.
     *
     * @param string $id
     * @return mixed
     */
    public function download(string $id)
    {
        $job = Access::ownJob($id);
        Access::authorize(ExportKind::group($job->kind), $job->id, 'download');
        if (!$job->isDownloadable()) {
            $this->addError('kind', (string) trans('Plugins/DataExport::lang.error.expired'));

            return null;
        }

        return $this->redirect((new ExportService)->downloadUrl($job));
    }

    /**
     * Render the screen.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function render()
    {
        $l = 'Plugins/DataExport::lang.';
        $kinds = [];
        foreach (Access::exportableKinds() as $kind) {
            $kinds[$kind] = gp247_language_render($l . 'kind.' . $kind);
        }
        $jobs = ExportJob::where('admin_id', Access::adminId())->orderByDesc('id')->paginate(15);

        return view('Plugins/DataExport::Admin.index', [
            'kinds' => $kinds,
            'jobs' => $jobs,
            'building' => $jobs->getCollection()->contains(fn ($j) => in_array($j->phase, ExportPhase::ACTIVE, true)),
            'encodings' => TargetEncoding::available(),
            'options' => FilterOptions::for($this->kind),
        ])->layout('gp247-admin::layouts.admin', ['title' => gp247_language_render($l . 'title')]);
    }

    /**
     * Refuse unless the chosen kind is known and the admin may export its group.
     *
     * @param string $action
     * @return void
     * @throws \GP247\Core\AdminShell\Domain\AuthorizationException
     */
    private function authorizeKind(string $action): void
    {
        $group = ExportKind::isKnown($this->kind) ? ExportKind::group($this->kind) : ExportKind::GROUP_CATALOG;
        Access::authorize($group, 'new', $action);
    }

    /**
     * Filters of the form, by the names ExportFilter checks.
     *
     * @return array<string, string>
     */
    private function filterInput(): array
    {
        $all = [
            'store' => $this->store, 'status' => $this->status, 'payment_status' => $this->paymentStatus, 'category' => $this->category,
            'date_from' => $this->dateFrom, 'date_to' => $this->dateTo,
        ];

        // WHY: a value typed for another kind (still in the form after switching kind) is not a filter of this one.
        return array_intersect_key($all, array_flip(ExportFilter::fields($this->kind)));
    }

    /**
     * One slice of a run; logs the run when it became ready.
     *
     * @param ExportJob $job
     * @return void
     */
    private function buildSlice(ExportJob $job): void
    {
        $result = (new SliceBuilder)->run($job->id);
        if ($result['state'] === SliceBuilder::DONE) {
            AuditLog::write('data_export_ready', $job->refresh());
        }
    }
}
