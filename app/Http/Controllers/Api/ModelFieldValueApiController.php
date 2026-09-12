<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ModelFieldIntrospector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ModelFieldValueApiController extends Controller
{
    /**
     * Scrive un singolo campo su un record, dato modello/id/campo/valore.
     * Se il campo è la foreign key di una relazione belongsTo (un "lookup"),
     * il valore passato viene prima risolto nel valore effettivo da scrivere
     * (tipicamente l'id del record correlato, o la sua owner key se diversa
     * da 'id' — es. Pratica::agente() punta a Fornitore.piva). Permette a
     * UnicoBPM di scrivere su questa app senza conoscerne lo schema interno.
     */
    public function update(Request $request, string $model, string $id, ModelFieldIntrospector $introspector): JsonResponse
    {
        $validated = $request->validate([
            'field' => ['required', 'string'],
            'value' => ['nullable'],
        ]);

        try {
            $modelClass = $introspector->resolveModelClass($model);
            $columnNames = collect($introspector->columns($model))->pluck('name');
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        if (! $columnNames->contains($validated['field'])) {
            return response()->json(['message' => "Campo '{$validated['field']}' inesistente su {$model}."], 422);
        }

        $record = $modelClass::find($id);

        if (! $record) {
            return response()->json(['message' => 'Record non trovato.'], 404);
        }

        try {
            $resolvedValue = $introspector->resolveFieldValue($model, $validated['field'], $validated['value']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $record->update([$validated['field'] => $resolvedValue]);

        return response()->json([
            'field' => $validated['field'],
            'value_stored' => $resolvedValue,
        ]);
    }
}
