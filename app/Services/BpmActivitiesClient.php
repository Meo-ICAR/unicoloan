<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Chiede a UnicoBPM l'elenco delle attività (processi) avviabili su un record,
 * passando i valori dei campi del record perché UnicoBPM possa valutare i propri
 * criteri di attivazione/esclusione senza dover leggere le tabelle di questa app.
 */
class BpmActivitiesClient
{
    /**
     * @return array<int, array{code: string, name: string, description: string|null}>
     */
    public function availableFor(string $modelType, string $modelId, array $fields): array
    {
        $bpmBaseUrl = (string) config('services.bpm.url');

        try {
            $response = Http::asJson()
                ->timeout(8)
                ->connectTimeout(4)
                ->post("{$bpmBaseUrl}/api/bpm/available-activities", [
                    'model_type' => $modelType,
                    'model_id' => $modelId,
                    'fields' => $fields,
                ]);
        } catch (\Throwable $e) {
            Log::warning('BPM activities: chiamata fallita per errore di rete.', [
                'model_type' => $modelType,
                'model_id' => $modelId,
            ]);

            return [];
        }

        if ($response->failed()) {
            Log::warning('BPM activities: risposta non valida da UnicoBPM.', [
                'model_type' => $modelType,
                'model_id' => $modelId,
                'status' => $response->status(),
            ]);

            return [];
        }

        return $response->json('activities') ?? [];
    }
}
