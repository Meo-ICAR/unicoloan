<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->comment('Archivio centralizzato dei documenti, allegati e file con supporto OCR e AI');

            // PK
            $table->uuid('id')->primary()->comment('UUID v4 del documento');

            // Relazioni Principali
            $table->uuid('company_id')->nullable()->comment('ID Tenant proprietario');

            // FIX: Usiamo uuidMorphs perché i soggetti (es. Company o Clienti) usano chiavi UUID
            $table->uuidMorphs('documentable');

            $table
                ->foreignId('document_type_id')
                ->nullable()
                ->constrained('document_types')
                ->nullOnDelete()
                ->comment('FK Tipo di documento');

            // Dati Logici
            $table->string('name')->nullable()->comment('Titolo logico o nome file del documento');
            $table->integer('training_hours')->nullable()->comment('Ore di formazione richieste');
            $table->enum('training_organization', ['interna', 'OAM', 'IVASS', 'PRIVACY'])->nullable()->comment('Formazione per organizzazione');
            $table->string('docnumber')->nullable()->comment('Numero protocollo o identificativo del documento');
            $table->string('spatie_collection', 100)->default('default')->comment('Nome della collection per Spatie Media Library');
            $table->string('document_url')->comment('URL pubblico o percorso del documento sul web/storage')->nullable();

            // Stati
            $table->string('status', 50)->default('uploaded')->comment('Stato del file: uploaded, verified, rejected, expired');
            $table->string('sync_status', 50)->default('local')->comment('Stato sincronizzazione cloud: local, syncing, synced, failed');

            // Sincronizzazione Cloud
            $table->string('source_app')->default('local')->comment('Applicazione di origine (es. local, sharepoint, google_drive)');
            $table->string('app_id')->nullable()->comment('ID univoco del file nel sistema di terze parti');
            $table->string('app_drive_id')->nullable()->comment('ID del Drive/Folder nel sistema di terze parti');
            $table->string('app_etag')->nullable()->comment('ETag per il controllo della versione in cloud');

            // AI e OCR
            $table->longText('extracted_text')->nullable()->comment('Testo grezzo estratto tramite OCR');
            $table->json('metadata')->nullable()->comment('Dati chiave estratti in formato JSON');
            $table->text('ai_abstract')->nullable()->comment("Riassunto testuale generato dall'Intelligenza Artificiale");
            $table->unsignedTinyInteger('ai_confidence_score')->nullable()->comment('Punteggio di affidabilita AI (0-100)');

            // Flags
            $table->boolean('is_monitored')->default(false)->comment('Scadenza monitorata nel tempo');
            $table->boolean('is_template')->default(false)->comment("Indica se è un modello vuoto fornito dall'azienda");
            $table->enum('doctype', ['modulo', 'informativa', 'procedura', 'template'])->nullable()->comment('Tipo documento: modulo, procedura, template');
            $table->string('cellposition')->nullable()->comment('Posizione della cella in cui si trova il documento');
            $table->boolean('is_signed')->default(false)->comment('Indica se il documento è stato firmato');
            $table->boolean('is_unique')->default(false)->comment("Se true, è l'unico documento ammesso in questa collection");
            $table->boolean('is_endMonth')->default(false)->comment("Approssima la scadenza all'ultimo giorno del mese");

            // Dettagli temporali e formali
            $table->string('emitted_by')->nullable()->comment('Ente di rilascio');
            $table->date('emitted_at')->nullable()->comment('Data di emissione del documento');
            $table->date('expires_at')->nullable()->comment('Data di scadenza del documento');
            $table->timestamp('delivered_at')->nullable()->comment('Data di consegna fisica o digitale');
            $table->timestamp('signed_at')->nullable()->comment('Data e ora della firma');

            // Testi e Note
            $table->text('description')->nullable()->comment("Descrizione pubblica visibile all'utente");
            $table->text('internal_notes')->nullable()->comment('Note interne visibili solo agli amministratori');
            $table->text('rejection_note')->nullable()->comment('Motivazione in caso di rifiuto del documento');

            // Audit Trails (Utenti)
            $table->unsignedBigInteger('user_id')->nullable()->comment('FK Utente o Cliente intestatario');
            $table->uuid('renewed_by')->nullable()->comment('FK Utente che ha aggiornato il documento');
            $table->unsignedBigInteger('uploaded_by')->nullable()->comment("FK Utente che ha eseguito l'upload");
            $table->unsignedBigInteger('verified_by')->nullable()->comment('FK Admin che ha verificato e approvato');
            $table->timestamp('verified_at')->nullable()->comment('Data e ora di verifica');
            $table->unsignedBigInteger('created_by')->nullable()->comment('FK Utente creatore del record');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('FK Utente che ha aggiornato il record');
            $table->unsignedBigInteger('deleted_by')->nullable()->comment('FK Utente che ha eliminato il record');

            // Sicurezza
            $table->string('file_hash', 64)->nullable()->comment('Hash SHA-256 del file per prevenire duplicati esatti');

            // Timestamps e SoftDeletes
            $table->timestamps();
            $table->softDeletes()->comment('Data di eliminazione logica del record');

            // Indici Espliciti Richiesti
            $table->index(['documentable_type', 'documentable_id'], 'doc_documentable_index');
            $table->index('company_id', 'doc_company_id_index');
            $table->index('expires_at', 'doc_expires_at_index');
            $table->index('status', 'doc_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
