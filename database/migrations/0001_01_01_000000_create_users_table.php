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
        Schema::create('users', function (Blueprint $table) {
            $table->comment('Registro degli utenti del sistema');
            $table->id()->comment('Identificativo univoco dell\'utente');
            $table->string('name')->comment('Nome completo dell\'utente');
            $table->string('email')->unique()->comment('Indirizzo email univoco usato per il login');
            $table->timestamp('email_verified_at')->nullable()->comment('Data e ora di verifica dell\'email');
            $table->string('password')->comment('Password hashata dell\'utente');
            $table->string('role')->default('user')->nullable()->comment('Ruolo assegnato all\'utente nel sistema');
            $table->rememberToken()->comment('Token per la funzionalità "ricordami" del login');
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->comment('Token temporanei per il reset della password');
            $table->string('email')->primary()->comment('Email dell\'utente che richiede il reset');
            $table->string('token')->comment('Token di reset generato');
            $table->timestamp('created_at')->nullable()->comment('Data e ora di generazione del token');
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->comment('Sessioni utente attive dell\'applicazione');
            $table->string('id')->primary()->comment('Identificativo univoco della sessione');
            $table->foreignId('user_id')->nullable()->index()->comment('Utente proprietario della sessione, se autenticato');
            $table->string('ip_address', 45)->nullable()->comment('Indirizzo IP da cui è stata avviata la sessione');
            $table->text('user_agent')->nullable()->comment('User agent del browser/client utilizzato');
            $table->longText('payload')->comment('Dati serializzati della sessione');
            $table->integer('last_activity')->index()->comment('Timestamp dell\'ultima attività registrata nella sessione');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
