<?php

namespace App\Filament\Unicofin\Resources\PraticaRequisitoOperativos\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class PraticaRequisitoOperativoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('pratica_id')
                    ->relationship('pratica', 'id')
                    ->required(),
                TextInput::make('pratica_requisito_id')
                    ->required()
                    ->numeric(),
                TextInput::make('stato')
                    ->required()
                    ->default('da_richiedere'),
                DateTimePicker::make('data_richiesta'),
                DateTimePicker::make('data_completamento'),
                Textarea::make('note')
                    ->columnSpanFull(),
            ]);
    }
}
