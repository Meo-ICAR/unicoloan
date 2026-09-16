<?php

use App\Http\Controllers\BpmBridgeController;
use App\Http\Controllers\DocumentDownloadController;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

Route::redirect('/', '/admin');

// Manuali operativi/tecnici (resources/manuals/*.html|*.md). Whitelist esplicita
// per evitare path traversal: nessun input utente libero sul nome file.
Route::get('/manuali/{manual}', function (string $manual) {
    $allowed = [
        'utente' => 'manuale-utente.html',
        'admin' => 'manuale-admin.html',
        'tecnico' => 'domain-model.md',
    ];

    if (! isset($allowed[$manual])) {
        abort(404);
    }

    $path = resource_path('manuals/'.$allowed[$manual]);

    if (! is_file($path)) {
        abort(404);
    }

    $contentType = str_ends_with($path, '.md') ? 'text/plain; charset=UTF-8' : 'text/html; charset=UTF-8';

    // Content-Disposition: inline forza la visualizzazione nel browser invece
    // del download (alcuni browser scaricano di default i tipi non-HTML).
    return Response::make(file_get_contents($path), HttpResponse::HTTP_OK, [
        'Content-Type' => $contentType,
        'Content-Disposition' => 'inline; filename="'.basename($path).'"',
        'X-Content-Type-Options' => 'nosniff',
    ]);
})->middleware('auth')->name('manuali.show');

// SSO dal BPM esterno. Throttling per limitare il brute force sul token.
Route::get('/bpm-landing/{subject_id}', [BpmBridgeController::class, 'handle'])
    ->middleware('throttle:10,1')
    ->name('bpm.landing');

// Download allegati: solo utenti autenticati (in precedenza era pubblico).
Route::get('/documents/{document}/download', DocumentDownloadController::class)
    ->middleware('auth')
    ->name('documents.download');
