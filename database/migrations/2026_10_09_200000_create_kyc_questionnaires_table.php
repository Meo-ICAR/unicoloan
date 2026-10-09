<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kyc_questionnaires')) {
            return;
        }

        Schema::create('kyc_questionnaires', function (Blueprint $table) {
            $table->comment('Questionari di adeguata verifica (QAV): un record per compilazione.');
            $table->id();
            $table->unsignedBigInteger('client_id')->index()->comment('mysql_proforma.clients.id');
            $table->foreignId('client_mandate_id')->nullable()->constrained('client_mandates')->nullOnDelete();
            $table->string('pratica_id', 64)->nullable()->comment('mysql_proforma.pratiches.id');
            $table->char('document_id', 36)->nullable()->index()->comment('documents.id: QAV generato');
            $table->string('pep_status')->nullable();
            $table->string('financing_purpose')->nullable();
            $table->string('risk_level')->nullable();
            $table->string('status')->default('draft')->index();
            $table->dateTime('compiled_at')->nullable();
            $table->string('verified_by')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->text('notes')->nullable();
            // persona fisica
            $table->string('economic_activity')->nullable();
            $table->string('activity_sector')->nullable();
            $table->string('activity_location')->nullable();
            $table->string('financing_nature')->nullable();
            $table->string('income_band')->nullable();
            $table->string('wealth_band')->nullable();
            $table->boolean('acts_for_third_party')->default(false);
            // persona giuridica
            $table->string('legal_nature')->nullable();
            $table->string('geographic_area')->nullable();
            $table->unsignedBigInteger('executor_client_id')->nullable()->comment('mysql_proforma.clients.id');
            $table->string('executor_link')->nullable();
            $table->string('executor_pep_status')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_questionnaires');
    }
};
