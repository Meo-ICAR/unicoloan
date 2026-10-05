<?php

namespace App\Filament\Utils;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class TableHelper
{
    /**
     * Genera un SelectFilter polimorfico dinamico basato sul MorphMap.
     */
    public static function polymorphicFilter(string $fieldName = 'documentable_type', string $label = 'Destinatari'): SelectFilter
    {
        return SelectFilter::make($fieldName)
            ->label($label)
            ->options(function () {
                // Dizionario delle tue traduzioni commerciali personalizzate
                $labels = [
                    'fornitore' => 'Produttore',
                    'company' => 'Azienda',
                    'employee' => 'Dipendente',
                    'audit' => 'Audit',
                    'complaint' => 'Reclamo',
                    'cliente' => 'Istituto',
                    'branch' => 'Filiale',
                ];

                // Filtra e mappa solo i modelli effettivamente presenti nel morphMap dell'applicazione
                return collect(Relation::morphMap())->mapWithKeys(fn ($className, $alias) => [
                    $alias => $labels[$alias] ?? ucfirst(class_basename($className)),
                ])->toArray();
            });
    }

    /**
     * Genera un TextColumn polimorfico dinamico con ricerca e ordinamento automatico,
     * mostrando esclusivamente il nome del soggetto controllato.
     */
    public static function polymorphicColumn(string $relationName = 'documentable', string $label = 'Soggetto Controllato'): TextColumn
    {
        $typeField = $relationName.'_type';
        $idField = $relationName.'_id';

        return TextColumn::make($relationName)
            ->label($label)
            // Cerca dinamicamente full_name o name sul record polimorfico collegato
            ->state(fn ($record) => $record->$relationName?->full_name ?? $record->$relationName?->name ?? 'N/D')

            // 1. RICERCA POLIMORFICA UNIVERSALE
            ->searchable(query: function (Builder $query, string $search) use ($relationName) {
                $allMorphAliases = array_keys(Relation::morphMap());

                $query->where(function (Builder $mainSubQuery) use ($relationName, $allMorphAliases, $search) {
                    $mainSubQuery->whereHasMorph($relationName, $allMorphAliases, function (Builder $q) use ($search) {
                        $modelInstance = $q->getModel();
                        $schema = $modelInstance->getConnection()->getSchemaBuilder();
                        $table = $modelInstance->getTable();

                        $q->where(function (Builder $subQuery) use ($search, $schema, $table) {
                            $hasName = $schema->hasColumn($table, 'name');
                            $hasFullName = $schema->hasColumn($table, 'full_name');

                            if ($hasName) {
                                $subQuery->where('name', 'like', "%{$search}%");
                            }

                            if ($hasFullName) {
                                if ($hasName) {
                                    $subQuery->orWhere('full_name', 'like', "%{$search}%");
                                } else {
                                    $subQuery->where('full_name', 'like', "%{$search}%");
                                }
                            }

                            if (! $hasName && ! $hasFullName) {
                                $subQuery->whereRaw('1 = 0');
                            }
                        });
                    });
                });
            })

            // 2. ORDINAMENTO POLIMORFICO DINAMICO CORRETTO
            ->sortable(query: function (Builder $query, string $direction) use ($typeField, $idField) {
                $cases = [];
                $bindings = [];

                foreach (Relation::morphMap() as $alias => $class) {
                    $modelInstance = new $class;
                    $schema = $modelInstance->getConnection()->getSchemaBuilder();
                    $table = $modelInstance->getTable();

                    // Identifichiamo la colonna testuale migliore su cui fare l'ordinamento
                    $sortColumn = null;
                    if ($schema->hasColumn($table, 'name')) {
                        $sortColumn = 'name';
                    } elseif ($schema->hasColumn($table, 'full_name')) {
                        $sortColumn = 'full_name';
                    }

                    if ($sortColumn) {
                        // Gestione dei nomi tabella che contengono già il database (es. proforma.clientis)
                        if (str_contains($table, '.')) {
                            $parts = explode('.', $table);
                            $qualifiedTable = '`'.implode('`.`', $parts).'`';
                        } else {
                            $dbName = $modelInstance->getConnection()->getDatabaseName();
                            $qualifiedTable = "`{$dbName}`.`{$table}`";
                        }

                        // Generiamo il pezzo di query condizionale con i backtick posizionati chirurgicamente
                        $cases[] = "WHEN `{$typeField}` = ? THEN (SELECT `{$sortColumn}` FROM {$qualifiedTable} WHERE {$qualifiedTable}.`id` = `{$idField}`)";
                        $bindings[] = $alias;
                    }
                }

                // Se abbiamo almeno una tabella valida su cui ordinare, compiliamo il CASE expression
                if (! empty($cases)) {
                    $caseSql = 'CASE '.implode(' ', $cases).' ELSE NULL END';

                    // Applichiamo l'ordinamento raw passando i bindings in totale sicurezza
                    $query->orderByRaw("({$caseSql}) {$direction}", $bindings);
                }

            });
    }
}
