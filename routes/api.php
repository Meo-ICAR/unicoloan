<?php

use App\Http\Controllers\Api\ModelFieldsApiController;
use App\Http\Controllers\Api\ModelFieldValueApiController;
use App\Http\Controllers\Api\PraticaApiController;
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
