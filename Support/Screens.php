<?php

namespace App\GP247\Plugins\DataExport\Support;

/**
 * What the admin screen needs while the plugin is active: the Livewire namespace, and the 2-second
 * slice pull kept out of core's Livewire operation log (each run step is logged by AuditLog).
 * No store-scoped segment: only the root store context uses the plugin.
 *
 * @aidlc-unit data-export
 * @aidlc-story US-data-export-access-control
 */
final class Screens
{
    /** The plugin's plumbing method pulled by wire:poll. */
    private const UNLOGGED_METHODS = ['dataExportTick'];

    /**
     * Register the Livewire namespace and the log exception.
     *
     * @return void
     */
    public static function register(): void
    {
        if (class_exists(\Livewire\Livewire::class)) {
            \Livewire\Livewire::addNamespace('DataExport', classNamespace: 'App\\GP247\\Plugins\\DataExport\\Admin\\Livewire');
        }
        $except = (string) config('gp247-config.admin.admin_log_livewire_except', \GP247\Core\AdminShell\Support\LivewireOperationLog::DEFAULT_EXCEPT);
        $methods = array_filter(array_map('trim', explode(',', $except)), static fn ($m) => $m !== '');
        config(['gp247-config.admin.admin_log_livewire_except' => implode(',', array_values(array_unique(array_merge($methods, self::UNLOGGED_METHODS))))]);
    }
}
