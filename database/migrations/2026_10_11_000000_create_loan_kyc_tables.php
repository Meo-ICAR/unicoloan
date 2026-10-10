<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adeguata verifica (QAV) di unicoloan. Vengono dopo le migration del pacchetto unico-core (che creano `documents`),
 * per questo la data è successiva a 2026_10_10_000008.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_questionnaires', function (Blueprint $table) {
            $table->comment('Questionari di adeguata verifica (QAV): un record per compilazione.');
            $table->id();
            $table->unsignedBigInteger('client_id')->index()->comment('clients.id');
            $table->unsignedBigInteger('client_mandate_id')->nullable()->index()->comment('client_mandates.id');
            $table->string('pratica_id', 64)->nullable()->comment('Pratica (id o codice, letta da Proforma)');
            $table->foreignId('document_id')->nullable()->index()->comment('documents.id: QAV generato')
                ->constrained('documents')->nullOnDelete();
            $table->string('pep_status')->nullable();
            $table->string('financing_purpose')->nullable();
            $table->string('risk_level')->nullable();
            $table->string('status')->default('draft')->index();
            $table->dateTime('compiled_at')->nullable();
            $table->string('verified_by')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('economic_activity')->nullable();
            $table->string('activity_sector')->nullable();
            $table->string('activity_location')->nullable();
            $table->string('financing_nature')->nullable();
            $table->string('income_band')->nullable();
            $table->string('wealth_band')->nullable();
            $table->boolean('acts_for_third_party')->default(false);
            $table->string('legal_nature')->nullable();
            $table->string('geographic_area')->nullable();
            $table->unsignedBigInteger('executor_client_id')->nullable()->comment('clients.id');
            $table->string('executor_link')->nullable();
            $table->string('executor_pep_status')->nullable();
            $table->timestamps();
        });

        Schema::create('kyc_beneficial_owners', function (Blueprint $table) {
            $table->comment('Titolari effettivi dichiarati nel QAV (max 3 stampabili).');
            $table->id();
            $table->foreignId('kyc_questionnaire_id')->constrained('kyc_questionnaires')->cascadeOnDelete();
            $table->unsignedTinyInteger('position')->default(1);
            $table->unsignedBigInteger('client_id')->index()->comment('persona fisica, clients.id');
            $table->decimal('shares_percentage', 5, 2)->nullable();
            $table->string('control_criterion');
            $table->string('pep_status')->nullable();
            $table->dateTime('declaration_signed_at')->nullable();
            $table->foreignId('document_id')->nullable()->comment('documents.id')
                ->constrained('documents')->nullOnDelete();
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_beneficial_owners');
        Schema::dropIfExists('kyc_questionnaires');
    }
};
