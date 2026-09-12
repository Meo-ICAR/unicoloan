<?php

namespace App\Filament\Unicofin\Resources\PraticaStatos\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PraticaStatoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('codice')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('ordine')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_rejected')
                    ->required(),
                Toggle::make('is_working')
                    ->required(),
                Toggle::make('is_estingued')
                    ->required(),
                TextInput::make('colore')
                    ->required()
                    ->default('gray'),
                TextInput::make('icona'),
            ]);
    }
}
