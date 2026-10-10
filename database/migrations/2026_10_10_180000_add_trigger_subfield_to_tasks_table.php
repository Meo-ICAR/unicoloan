<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tasks', 'trigger_subfield')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->string('trigger_subfield')->nullable()->after('trigger_value')->comment('Secondo campo del modello da controllare (es. stato_pratica), in aggiunta a trigger_field');
            });
        }

        if (! Schema::hasColumn('tasks', 'trigger_subvalue')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->string('trigger_subvalue')->nullable()->after('trigger_subfield')->comment('Valore che trigger_subfield deve avere (uguale a)');
            });
        }
    }

    public function down(): void
    {
        foreach (['trigger_subvalue', 'trigger_subfield'] as $column) {
            if (Schema::hasColumn('tasks', $column)) {
                Schema::table('tasks', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
