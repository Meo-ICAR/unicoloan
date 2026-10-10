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
        Schema::create('mail_accounts', function (Blueprint $table) {
            $table->comment('Configurazioni dei server POP3/IMAP e SMTP per le caselle email e PEC');

            $table->id()->comment('ID univoco dell account');
            $table->string('name')->nullable()->comment('Nome descrittivo dell account (es. Amministrazione PEC)');

            $table->string('email_address')->unique()->comment('Indirizzo email completo');
            $table->string('email_reply_to')->nullable()->comment('Indirizzo email di reply-to');
            $table->boolean('is_pec')->default(false)->comment('Indica se l account è una PEC (1) o una mail standard (0)');

            // Configurazione Posta in Entrata
            $table->enum('incoming_protocol', ['pop3', 'imap'])->default('pop3')->comment('Protocollo di lettura: pop3 o imap');
            $table->string('incoming_host')->comment('Host del server di posta in arrivo (es. pop3.pec.it)');
            $table->integer('incoming_port')->comment('Porta del server di posta in arrivo (es. 995)');
            $table->string('incoming_username')->comment('Username per la posta in arrivo');
            $table->text('incoming_password')->comment('Password criptata per la posta in arrivo');
            $table->enum('incoming_encryption', ['none', 'ssl', 'tls'])->default('ssl')->comment('Tipo di crittografia per la posta in arrivo');

            // Configurazione Posta in Uscita (SMTP)
            $table->string('smtp_host')->comment('Host del server di posta in uscita (es. smtps.pec.it)');
            $table->integer('smtp_port')->comment('Porta del server di posta in uscita (es. 465)');
            $table->string('smtp_username')->comment('Username per la posta in uscita');
            $table->text('smtp_password')->comment('Password criptata per la posta in uscita');
            $table->enum('smtp_encryption', ['none', 'ssl', 'tls'])->default('ssl')->comment('Tipo di crittografia per la posta in uscita');

            // Stato e Relazione Polimorfica Stringa/UUID
            $table->boolean('is_active')->default(true)->comment('Indica se l account è attualmente attivo');
            $table->string('mailable_type')->nullable()->comment('Classe del modello polimorfico associato');
            $table->string('mailable_id', 255)->nullable()->comment('ID di tipo stringa/UUID del record polimorfico');

            // Timestamps e SoftDeletes
            $table->timestamps();
            $table->softDeletes()->comment('Data di eliminazione logica del record');

            // Indice composto polimorfico
            $table->index(['mailable_type', 'mailable_id'], 'mail_accounts_mailable_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_accounts');
    }
};
