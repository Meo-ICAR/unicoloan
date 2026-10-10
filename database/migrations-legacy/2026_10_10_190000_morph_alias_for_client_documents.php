<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Client e Pratica sono ora nel morphMap ('client', 'pratica'): i documenti gia' salvati con il nome completo
     * della classe passano all'alias. Le tabelle di proforma (fatture, indirizzi) hanno i propri morph e non si toccano.
     */
    public function up(): void
    {
        DB::table('documents')->where('documentable_type', 'App\\Models\\Client')->update(['documentable_type' => 'client']);
        DB::table('documents')->where('documentable_type', 'App\\Models\\PROFORMA\\Pratica')->update(['documentable_type' => 'pratica']);
    }

    public function down(): void
    {
        DB::table('documents')->where('documentable_type', 'client')->update(['documentable_type' => 'App\\Models\\Client']);
        DB::table('documents')->where('documentable_type', 'pratica')->update(['documentable_type' => 'App\\Models\\PROFORMA\\Pratica']);
    }
};
