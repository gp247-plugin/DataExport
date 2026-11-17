<?php
/**
 * Boots the Data Export plugin. Everything that runs is registered only while the plugin is
 * active: settings, the CLI command and the admin screen (views, Livewire namespace).
 */

$config = json_decode((string) file_get_contents(__DIR__ . '/gp247.json'), true);
$extensionPath = $config['configGroup'] . '/' . $config['configKey'];

$this->loadTranslationsFrom(__DIR__ . '/Lang', $extensionPath);

if (gp247_extension_check_active($config['configGroup'], $config['configKey'])) {
    $this->mergeConfigFrom(__DIR__ . '/config.php', $extensionPath);
    $this->commands([\App\GP247\Plugins\DataExport\Console\DataExportRun::class]);
    $this->loadViewsFrom(__DIR__ . '/Views', $extensionPath);
    \App\GP247\Plugins\DataExport\Support\Screens::register();
}
