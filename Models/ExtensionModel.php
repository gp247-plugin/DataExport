<?php

namespace App\GP247\Plugins\DataExport\Models;

use Illuminate\Support\Facades\Schema;

/**
 * Schema of the Data Export plugin: one table of runs (Pattern A — created only when missing, so
 * install and update can run again safely). The files live in storage/app/data-export, never in
 * the table.
 *
 * @aidlc-unit data-export
 * @aidlc-story US-data-export-install-lifecycle
 */
class ExtensionModel
{
    public const TABLE_JOB = 'data_export_job';

    /**
     * Create the table of runs when it is missing.
     *
     * @return void
     */
    public function installExtension(): void
    {
        $schema = Schema::connection(GP247_DB_CONNECTION);
        $table = GP247_DB_PREFIX . self::TABLE_JOB;
        if ($schema->hasTable($table)) {
            return;
        }
        $schema->create($table, function ($table) {
            $table->string('id', 26)->primary();
            $table->string('admin_id', 64)->index();
            $table->string('kind', 20);
            $table->text('filters')->nullable();
            $table->string('encoding', 20);
            $table->string('phase', 20)->index();
            $table->unsignedInteger('expected_rows')->default(0);
            $table->unsignedInteger('written_rows')->default(0);
            $table->unsignedInteger('replaced_cells')->default(0);
            $table->unsignedInteger('first_replaced_row')->nullable();
            $table->text('cursor')->nullable();
            $table->string('lease_token', 40)->nullable();
            $table->dateTime('lease_until')->nullable();
            $table->string('error', 255)->nullable();
            $table->unsignedBigInteger('file_bytes')->nullable();
            $table->dateTime('ready_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('downloaded_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Keep the table on uninstall: it holds no personal data, and the run list of a re-installed
     * plugin still shows who exported what.
     *
     * @return void
     */
    public function uninstallExtension(): void
    {
    }
}
