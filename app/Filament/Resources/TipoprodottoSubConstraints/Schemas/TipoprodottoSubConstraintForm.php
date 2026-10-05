<?php

namespace App\Filament\Resources\TipoprodottoSubConstraints\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TipoprodottoSubConstraintForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('tipoprodotto_id')
                    ->label('Prodotto')
                    ->numeric(),
                TextInput::make('tipoprodotto_sub_id')
                    ->label('Sottoprodotto')
                    ->numeric(),
                TextInput::make('clienti_id')
                    ->label('Banca'),
                Select::make('role_id')
                    ->label('Ruolo')
                    ->relationship('role', 'name'),
                TextInput::make('min_age')
                    ->label('Età Minima')
                    ->numeric(),
                TextInput::make('max_age_at_maturity')
                    ->label('Età Massima a Scadenza')
                    ->numeric(),
                TextInput::make('min_amount')
                    ->label('Importo Minimo')
                    ->numeric(),
                TextInput::make('max_amount')
                    ->label('Importo Massimo')
                    ->numeric(),
                TextInput::make('min_duration_months')
                    ->label('Durata Minima (mesi)')
                    ->numeric(),
                TextInput::make('max_duration_months')
                    ->label('Durata Massima (mesi)')
                    ->numeric(),
                TextInput::make('min_employment_months')
                    ->label('Anzianità Lavorativa Minima (mesi)')
                    ->numeric(),
                TextInput::make('max_debt_to_income_ratio')
                    ->label('Rapporto Rata/Reddito Massimo')
                    ->numeric(),
                TextInput::make('max_ltv_percentage')
                    ->label('LTV Massimo (%)')
                    ->numeric(),
                TextInput::make('allowed_employment_types')
                    ->label('Tipologie Contrattuali Ammesse'),
                TextInput::make('additional_rules_json')
                    ->label('Regole Aggiuntive (JSON)'),
                Textarea::make('additional_notes')
                    ->label('Note Aggiuntive')
                    ->columnSpanFull(),
            ]);
    }
}
