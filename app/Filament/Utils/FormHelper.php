<?php

namespace App\Filament\Utils;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get; // Manteniamo il tuo import specifico
use Illuminate\Database\Eloquent\Relations\Relation;

class FormHelper
{
    /**
     * Genera un Select precompilato con i modelli registrati nel MorphMap.
     */
    public static function polymorphicSelect(string $name = 'taskable', string $label = 'Collegata a'): Select
    {
        return Select::make($name)
            ->label($label)
            ->options(function () {
                $labels = [
                    'audit' => 'Audit',
                    'company' => 'Azienda',
                    'employee' => 'Dipendente',
                    'cliente' => 'Mandante',
                    'fornitore' => 'Produttore',
                    'branch' => 'Filiale',
                ];

                return collect(Relation::morphMap())->mapWithKeys(fn ($className, $alias) => [
                    $alias => $labels[$alias] ?? ucfirst(class_basename($className)),
                ])->toArray();
            })
            ->default('fornitore') // Imposta il valore predefinito su 'fornitore'
            ->searchable();

    }

    /**
     * Genera un campo di testo che appare e diventa obbligatorio
     * SOLO quando un altro campo di tipo Select assume il valore 'equals'.
     */
    public static function conditionalValueField(string $name, string $label, string $dependsOnField): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->placeholder('es. valore_specifico')
            ->visible(fn (Get $get) => $get($dependsOnField) === 'equals')
            ->required(fn (Get $get) => $get($dependsOnField) === 'equals');
    }
}
