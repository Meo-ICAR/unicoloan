<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ModelFieldIntrospector;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class ModelFieldsApiController extends Controller
{
    /**
     * Espone le colonne (con i commenti reali del DB MySQL) e le relazioni
     * lookup di un modello, così UnicoBPM può costruire una select dei campi
     * da includere/escludere in fase di configurazione di un processo, senza
     * doversi collegare direttamente alle tabelle di questa app.
     */
    public function show(string $model, ModelFieldIntrospector $introspector): JsonResponse
    {
        try {
            $columns = $introspector->columns($model);
            $lookups = $introspector->lookups($model);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json([
            'model' => $model,
            'columns' => $columns,
            'lookups' => $lookups,
        ]);
    }
}
