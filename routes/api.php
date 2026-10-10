<?php

use App\Http\Controllers\Api\AgentRequestController;
use App\Http\Controllers\Api\ModelFieldsApiController;
use App\Http\Controllers\Api\ModelFieldValueApiController;
use App\Http\Controllers\Api\PraticaApiController;
use App\Http\Controllers\Api\SignatureWebhookController;
use App\Http\Controllers\Api\UserLookupApiController;
use Illuminate\Support\Facades\Route;

// Consumato da UnicoBPM, che non ha un proprio modello Pratica: nessuna
// autenticazione per ora (ambiente non di produzione), da aggiungere prima
// del rilascio.
Route::get('/pratiche/{id}', [PraticaApiController::class, 'show'])->name('api.pratiche.show');

// Introspezione/scrittura generica usata da UnicoBPM per configurare i
// processi (select dei campi disponibili) e per scrivere un campo senza
// accedere direttamente ai modelli di questa app.
Route::get('/models/{model}/fields', [ModelFieldsApiController::class, 'show'])->name('api.models.fields');
Route::patch('/models/{model}/{id}', [ModelFieldValueApiController::class, 'update'])->name('api.models.update-field');

// Consumato da UnicoBPM per verificare se l'utente loggato ha un account anche qui.
Route::get('/users/lookup', [UserLookupApiController::class, 'show'])->name('api.users.lookup');

// Webhook dei provider di firma: autenticato dalla verifica della firma del provider, risponde subito e accoda la riconciliazione.
Route::post('/signature/webhook/{provider}', SignatureWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('api.signature.webhook');

// Richieste di finanziamento consegnate da app esterne (unicoagent): cliente, pratica e documenti. Token Bearer e firma
// della richiesta (vedi config/agent_api.php e php artisan agent-api:client). Gli invii sono idempotenti per «riferimento».
Route::prefix('agente/v1')->middleware(['api.client', 'throttle:120,1'])->group(function () {
    Route::post('/richieste', [AgentRequestController::class, 'store'])->name('api.agente.richieste.store');
    Route::get('/richieste/{riferimento}', [AgentRequestController::class, 'show'])->name('api.agente.richieste.show');
    Route::post('/richieste/{riferimento}/documenti', [AgentRequestController::class, 'upload'])->name('api.agente.richieste.documenti');
    Route::get('/tipi-documento', [AgentRequestController::class, 'documentTypes'])->name('api.agente.tipi-documento');
});
