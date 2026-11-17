<?php
/**
 * Data Export DEFAULT settings. This file belongs to the plugin and is overwritten by every
 * update — to change a value on a site, set it in the site's own config (key
 * `Plugins/DataExport.<name>`), never here.
 */
return [
    // Hours a ready file can be downloaded before it is deleted.
    'retention_hours' => 24,

    // Minutes a download link stays valid (a new link is made on every click).
    'link_minutes' => 5,

    // Runs one admin may have waiting or being built at the same time.
    'max_building_per_admin' => 1,

    // Records read per batch while building a file.
    'batch_rows' => 500,

    // A run being built with no slice for this many minutes is marked failed.
    'idle_minutes' => 15,

    // Days the list keeps a finished run (without its file).
    'log_days' => 90,
];
