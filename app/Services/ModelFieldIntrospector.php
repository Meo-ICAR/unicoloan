<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PROFORMA\Clienti;
use App\Models\PROFORMA\Fornitore;
use App\Models\PROFORMA\Pratica;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;

/**
 * Espone la struttura di un modello (colonne + commenti reali da MySQL, e
 * relazioni belongsTo trattate come "lookup") a consumatori esterni come
 * UnicoBPM, che non ha accesso diretto a questi modelli/tabelle e le usa per
 * costruire le select di configurazione dei processi (quali campi includere/
 * escludere) e per risolvere un valore "umano" nel valore effettivo da
 * scrivere su un campo lookup.
 */
class ModelFieldIntrospector
{
    /**
     * @var array<string, class-string<Model>>
     */
    private const MODEL_MAP = [
        'fornitore' => Fornitore::class,
        'cliente' => Clienti::class,
        'employee' => Employee::class,
        'pratica' => Pratica::class,
    ];

    public function resolveModelClass(string $modelType): string
    {
        $modelClass = self::MODEL_MAP[$modelType] ?? null;

        if (! $modelClass) {
            throw new InvalidArgumentException("Modello sconosciuto: {$modelType}");
        }

        return $modelClass;
    }

    /**
     * @return array<int, array{name: string, type: string, comment: string|null, nullable: bool}>
     */
    public function columns(string $modelType): array
    {
        $modelClass = self::MODEL_MAP[$modelType] ?? throw new InvalidArgumentException("Modello sconosciuto: {$modelType}");

        return $this->columnsForClass($modelClass);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<int, array{name: string, type: string, comment: string|null, nullable: bool}>
     */
    private function columnsForClass(string $modelClass): array
    {
        $model = new $modelClass;

        $connection = $model->getConnectionName() ?? config('database.default');
        $database = config("database.connections.{$connection}.database");
        $table = $model->getTable();
        // Alcune tabelle sono già qualificate con lo schema (es. 'unicooam.clienti_oam');
        // qui ci serve solo il nome della tabella senza prefisso per interrogare information_schema.
        $table = str_contains($table, '.') ? substr($table, strpos($table, '.') + 1) : $table;

        $rows = DB::connection($connection)->select(
            'select column_name as `name`, column_type as `type`, column_comment as `comment`, is_nullable as `nullable`
             from information_schema.columns
             where table_schema = ? and table_name = ?
             order by ordinal_position',
            [$database, $table]
        );

        return collect($rows)->map(fn ($row) => [
            'name' => $row->name,
            'type' => $row->type,
            'comment' => $row->comment !== '' ? $row->comment : null,
            'nullable' => $row->nullable === 'YES',
        ])->all();
    }

    /**
     * Relazioni belongsTo del modello, trattate come "lookup": scrivere sul
     * campo `foreign_key` di fatto punta a un record del modello correlato,
     * identificato dalla sua `owner_key` (non necessariamente 'id' — es.
     * Pratica::agente() punta a Fornitore.piva, non Fornitore.id).
     *
     * @return array<int, array{relation: string, foreign_key: string, owner_key: string, related_model: string}>
     */
    public function lookups(string $modelType): array
    {
        $modelClass = self::MODEL_MAP[$modelType] ?? throw new InvalidArgumentException("Modello sconosciuto: {$modelType}");
        $model = new $modelClass;
        $reflection = new ReflectionClass($modelClass);

        $lookups = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getNumberOfParameters() > 0 || $method->isStatic() || $method->class !== $modelClass) {
                continue;
            }

            try {
                $result = $method->invoke($model);
            } catch (\Throwable) {
                continue;
            }

            if (! $result instanceof BelongsTo) {
                continue;
            }

            $lookups[] = [
                'relation' => $method->getName(),
                'foreign_key' => $result->getForeignKeyName(),
                'owner_key' => $result->getOwnerKeyName(),
                'related_model' => array_search(get_class($result->getRelated()), self::MODEL_MAP, true) ?: get_class($result->getRelated()),
            ];
        }

        return $lookups;
    }

    /**
     * Se `field` è la foreign key di una relazione belongsTo, risolve $value
     * nel valore effettivo da scrivere: prova prima un match diretto contro
     * la owner key della relazione, poi contro le colonne "display" più
     * comuni (name, nome, codice, code) se la owner key non lo è già.
     * Ritorna $value invariato se il campo non è un lookup.
     *
     * @throws InvalidArgumentException se il campo è un lookup ma il valore non è risolvibile
     */
    public function resolveFieldValue(string $modelType, string $field, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $lookup = collect($this->lookups($modelType))->firstWhere('foreign_key', $field);

        if (! $lookup) {
            return $value;
        }

        $relatedModelType = $lookup['related_model'];
        $relatedClass = self::MODEL_MAP[$relatedModelType] ?? $relatedModelType;
        $ownerKey = $lookup['owner_key'];

        $existingColumns = collect($this->columnsForClass($relatedClass))->pluck('name');
        $displayColumns = collect([$ownerKey, 'name', 'nome', 'codice', 'code'])
            ->unique()
            ->filter(fn (string $column) => $existingColumns->contains($column));

        foreach ($displayColumns as $column) {
            /** @var Model $related */
            $related = (new $relatedClass)->newQuery()->where($column, $value)->first();

            if ($related) {
                return $related->{$ownerKey};
            }
        }

        throw new InvalidArgumentException(
            "Impossibile risolvere il valore '{$value}' per il campo lookup '{$field}' su {$modelType}."
        );
    }
}
