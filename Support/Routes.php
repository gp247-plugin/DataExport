<?php

namespace App\GP247\Plugins\DataExport\Support;

use App\GP247\Plugins\DataExport\Admin\Livewire\ExportIndex;
use App\GP247\Plugins\DataExport\Http\DownloadController;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Support\Facades\Route;

/**
 * The admin screen and the download route. The download path carries the permission group
 * (`<admin>/data-export/<group>/<id>/download`) so the admin middleware checks the group's
 * permission (ANY::…/<group>/*) before the controller checks owner, phase and expiry; the link
 * must also carry a valid signature.
 *
 * @aidlc-unit data-export
 * @aidlc-story US-data-export-access-control
 * @aidlc-story US-data-export-download-retention
 */
final class Routes
{
    /**
     * Register the admin routes.
     *
     * @return void
     */
    public static function register(): void
    {
        Route::group(
            [
                'prefix' => GP247_ADMIN_PREFIX . '/' . Access::URI,
                'middleware' => GP247_ADMIN_MIDDLEWARE,
            ],
            static function (): void {
                Route::get('/', ExportIndex::class)->name('admin_data_export.index');
                Route::get('/{group}/{id}/download', DownloadController::class)
                    ->where(['group' => 'catalog|customers|orders', 'id' => '[0-9a-z]{26}'])
                    ->middleware(ValidateSignature::class)
                    ->name('admin_data_export.download');
            }
        );
    }
}
