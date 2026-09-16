<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('socialite_users', function (Blueprint $table) {
            $table->comment('Account social collegati agli utenti per l\'autenticazione OAuth');
            $table->id()->comment('Identificativo univoco del collegamento social');

            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate()->comment('Utente proprietario dell\'account social collegato');
            $table->string('provider')->comment('Nome del provider OAuth (es. google, facebook)');
            $table->string('provider_id')->comment('Identificativo dell\'utente presso il provider OAuth');
            $table->string('email')->nullable()->comment('Email associata all\'account social');
            $table->string('avatar')->nullable()->comment('URL dell\'avatar restituito dal provider social');
            $table->boolean('is_personal')->default(false)->comment('Indica se il collegamento è un account personale');

            $table->timestamps();

            $table->unique([
                'provider',
                'provider_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('socialite_users');
    }
};
