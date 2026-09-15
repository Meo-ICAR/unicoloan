<?php

namespace App\Filament\Resources\ProvvigioniRules\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProvvigioniRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Toggle::make('coordinamento')
                    ->label('Coordinamento')
                    ->required(),
                Toggle::make('iscliente')
                    ->label('È Cliente')
                    ->required(),
                TextInput::make('tipo_provvigioni')
                    ->label('Tipo Provvigione')
                    ->required()
                    ->default('lordo'),
                TextInput::make('value')
                    ->label('Valore')
                    ->numeric()
                    ->default(0.0),
                DatePicker::make('valid_from')
                    ->label('Valido Dal'),
                DatePicker::make('valid_to')
                    ->label('Valido Al'),
                Textarea::make('notes')
                    ->label('Note')
                    ->columnSpanFull(),
            ]);
    }
}
