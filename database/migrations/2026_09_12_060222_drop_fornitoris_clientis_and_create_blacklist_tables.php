<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * These tables live in the `mysql_proforma` connection alongside `clientis`
     * and `fornitoris`, so they are managed explicitly on that connection rather
     * than the app's default one. That connection is a shared, persistent
     * database that `RefreshDatabase`/`LazilyRefreshDatabase` never resets (only
     * the app's own default-connection test database is dropped/recreated
     * between test runs), so every step here must be idempotent: the test suite
     * re-runs every migration's `up()` from scratch on each `migrate:fresh`,
     * and this one would otherwise fail with "table already exists" on the
     * second run.
     */
    public function up(): void
    {
        $schema = Schema::connection('mysql_proforma');

        // `fornitoris_clientis` was an empty, unreferenced scaffold table.
        // The blacklist relationship it was meant to represent is replaced below.
        $schema->dropIfExists('fornitoris_clientis');

        if (! $schema->hasTable('blacklist_clienti_fornitori')) {
            $schema->create('blacklist_clienti_fornitori', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('cliente_id');
                $table->uuid('fornitore_id');
                $table->text('motivo')->nullable();
                $table->date('data_inizio')->nullable();
                $table->date('data_fine')->nullable();
                $table->timestamps();

                $table->foreign('cliente_id')->references('id')->on('clientis')->cascadeOnDelete();
                $table->foreign('fornitore_id')->references('id')->on('fornitoris')->cascadeOnDelete();
                $table->index(['fornitore_id', 'cliente_id']);
            });
        }

        // `employees` lives in the app's own database (a different connection/server
        // schema than `clientis`), so `employee_id` is stored without a DB-level
        // foreign key — consistent with how `pratiches` already references
        // fornitoris/clientis by denormalized value rather than a cross-database FK.
        if (! $schema->hasTable('blacklist_clienti_employees')) {
            $schema->create('blacklist_clienti_employees', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('cliente_id');
                $table->unsignedBigInteger('employee_id');
                $table->text('motivo')->nullable();
                $table->date('data_inizio')->nullable();
                $table->date('data_fine')->nullable();
                $table->timestamps();

                $table->foreign('cliente_id')->references('id')->on('clientis')->cascadeOnDelete();
                $table->index(['employee_id', 'cliente_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql_proforma')->dropIfExists('blacklist_clienti_employees');
        Schema::connection('mysql_proforma')->dropIfExists('blacklist_clienti_fornitori');

        Schema::connection('mysql_proforma')->create('fornitoris_clientis', function (Blueprint $table) {
            $table->id();
            $table->uuid('fornitori_id');
            $table->uuid('clienti_id');
            $table->string('name')->nullable();

            $table->foreign('fornitori_id')->references('id')->on('fornitoris');
            $table->foreign('clienti_id')->references('id')->on('clientis');
        });
    }
};
