<?php

use App\Http\Controllers\Api\PraticaApiController;
use Illuminate\Support\Facades\Route;

// Consumato da UnicoBPM, che non ha un proprio modello Pratica: nessuna
// autenticazione per ora (ambiente non di produzione), da aggiungere prima
// del rilascio.
Route::get('/pratiche/{id}', [PraticaApiController::class, 'show'])->name('api.pratiche.show');
