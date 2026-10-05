<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PROFORMA\Pratica;
use App\Services\BlacklistChecker;
use Illuminate\Http\JsonResponse;

class PraticaApiController extends Controller
{
    /**
     * Espone a UnicoBPM i dati di una pratica necessari per decidere se farla
     * avanzare, incluso lo stato di blacklist dell'agente per la banca collegata.
     * UnicoBPM non ha un proprio modello Pratica: chiede questi dati via API
     * invece di leggere direttamente la tabella `pratiches`.
     */
    public function show(string $id, BlacklistChecker $blacklistChecker): JsonResponse
    {
        $pratica = Pratica::find($id);

        if (! $pratica) {
            return response()->json(['message' => 'Pratica non trovata.'], 404);
        }

        $agenteBlacklistato = $blacklistChecker->isAgenteNameBlacklistedForBancaName(
            $pratica->denominazione_agente,
            $pratica->denominazione_banca,
        );

        return response()->json([
            'id' => $pratica->id,
            'codice_pratica' => $pratica->codice_pratica,
            'stato_pratica' => $pratica->stato_pratica,
            'denominazione_agente' => $pratica->denominazione_agente,
            'denominazione_banca' => $pratica->denominazione_banca,
            'agente_blacklistato' => $agenteBlacklistato,
        ]);
    }
}
