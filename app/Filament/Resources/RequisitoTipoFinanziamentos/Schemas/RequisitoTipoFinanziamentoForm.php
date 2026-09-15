<?php

namespace App\Filament\Resources\RequisitoTipoFinanziamentos\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class RequisitoTipoFinanziamentoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('tipoprodotto_id')
                    ->label('Tipo prodotto')
                    ->numeric(),
                TextInput::make('tipoprodotto_sub_id')
                    ->label('Sottotipo prodotto')
                    ->numeric(),
                TextInput::make('pratica_requisito_id')
                    ->label('Requisito pratica')
                    ->required()
                    ->numeric(),
                Toggle::make('obbligatorio')
                    ->label('Obbligatorio')
                    ->required(),
                TextInput::make('ordine')
                    ->label('Ordine')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
