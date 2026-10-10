<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kyc_beneficial_owners')) {
            return;
        }

        Schema::create('kyc_beneficial_owners', function (Blueprint $table) {
            $table->comment('Titolari effettivi dichiarati nel QAV (max 3 stampabili).');
            $table->id();
            $table->foreignId('kyc_questionnaire_id')->constrained('kyc_questionnaires')->cascadeOnDelete();
            $table->unsignedTinyInteger('position')->default(1);
            $table->unsignedBigInteger('client_id')->index()->comment('persona fisica, mysql_proforma.clients.id');
            $table->decimal('shares_percentage', 5, 2)->nullable();
            $table->string('control_criterion');
            $table->string('pep_status')->nullable();
            $table->dateTime('declaration_signed_at')->nullable();
            $table->char('document_id', 36)->nullable()->comment('documents.id');
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_beneficial_owners');
    }
};
