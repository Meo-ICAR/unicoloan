<?php

namespace App\Filament\Resources\PraticaStatos\Schemas;

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
                    ->label('Codice')
                    ->required(),
                TextInput::make('name')
                    ->label('Nome')
                    ->required(),
                TextInput::make('ordine')
                    ->label('Ordine')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_rejected')
                    ->label('Rifiutata')
                    ->required(),
                Toggle::make('is_working')
                    ->label('In lavorazione')
                    ->required(),
                Toggle::make('is_estingued')
                    ->label('Estinta')
                    ->required(),
                TextInput::make('colore')
                    ->label('Colore')
                    ->required()
                    ->default('gray'),
                TextInput::make('icona')
                    ->label('Icona'),
            ]);
    }
}
