<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('pdf_modules', 'document_type_id')) {
            return;
        }

        Schema::table('pdf_modules', function (Blueprint $table) {
            $table->foreignId('document_type_id')
                ->nullable()
                ->after('file_path')
                ->constrained('document_types')
                ->nullOnDelete()
                ->comment('Tipo documento del catalogo aziendale a cui appartiene il modulo');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('pdf_modules', 'document_type_id')) {
            return;
        }

        Schema::table('pdf_modules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('document_type_id');
        });
    }
};
