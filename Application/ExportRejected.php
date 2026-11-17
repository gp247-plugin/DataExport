<?php

namespace App\GP247\Plugins\DataExport\Application;

/**
 * A request the plugin refuses for a reason the user can act on (bad filter, nothing to export,
 * a run already being built). The message is the translated text; $key names it for tests and
 * the screen, $field the filter it concerns.
 *
 * @aidlc-unit data-export
 * @aidlc-story US-data-export-filters
 */
final class ExportRejected extends \RuntimeException
{
    /**
     * @param string $key Language key under Plugins/DataExport::lang.
     * @param array<string, string> $replace
     * @param string $field Filter field the reason concerns, or ''.
     */
    public function __construct(public readonly string $key, array $replace = [], public readonly string $field = '')
    {
        parent::__construct((string) trans('Plugins/DataExport::lang.' . $key, $replace));
    }
}
