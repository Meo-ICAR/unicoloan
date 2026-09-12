<?php

namespace App\Filament\Unicofin\Resources\TipoprodottoSubConstraints\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class TipoprodottoSubConstraintForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('tipoprodotto_id')
                    ->numeric(),
                TextInput::make('tipoprodotto_sub_id')
                    ->numeric(),
                TextInput::make('clienti_id'),
                Select::make('role_id')
                    ->relationship('role', 'name'),
                TextInput::make('min_age')
                    ->numeric(),
                TextInput::make('max_age_at_maturity')
                    ->numeric(),
                TextInput::make('min_amount')
                    ->numeric(),
                TextInput::make('max_amount')
                    ->numeric(),
                TextInput::make('min_duration_months')
                    ->numeric(),
                TextInput::make('max_duration_months')
                    ->numeric(),
                TextInput::make('min_employment_months')
                    ->numeric(),
                TextInput::make('max_debt_to_income_ratio')
                    ->numeric(),
                TextInput::make('max_ltv_percentage')
                    ->numeric(),
                TextInput::make('allowed_employment_types'),
                TextInput::make('additional_rules_json'),
                Textarea::make('additional_notes')
                    ->columnSpanFull(),
            ]);
    }
}
