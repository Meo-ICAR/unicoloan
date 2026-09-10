<?php

use App\Http\Controllers\BpmBridgeController;
use App\Http\Controllers\DocumentDownloadController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

// SSO dal BPM esterno. Throttling per limitare il brute force sul token.
Route::get('/bpm-landing/{subject_id}', [BpmBridgeController::class, 'handle'])
    ->middleware('throttle:10,1')
    ->name('bpm.landing');

// Download allegati: solo utenti autenticati (in precedenza era pubblico).
Route::get('/documents/{document}/download', DocumentDownloadController::class)
    ->middleware('auth')
    ->name('documents.download');
