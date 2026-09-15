<?php

namespace App\Filament\Resources\PraticaRequisitoOperativos\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PraticaRequisitoOperativoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('pratica_id')
                    ->label('Pratica')
                    ->relationship('pratica', 'id')
                    ->required(),
                TextInput::make('pratica_requisito_id')
                    ->label('Requisito pratica')
                    ->required()
                    ->numeric(),
                TextInput::make('stato')
                    ->label('Stato')
                    ->required()
                    ->default('da_richiedere'),
                DateTimePicker::make('data_richiesta')
                    ->label('Data richiesta'),
                DateTimePicker::make('data_completamento')
                    ->label('Data completamento'),
                Textarea::make('note')
                    ->label('Note')
                    ->columnSpanFull(),
            ]);
    }
}
