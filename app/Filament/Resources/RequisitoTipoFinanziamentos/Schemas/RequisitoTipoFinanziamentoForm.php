<?php

namespace App\Filament\Unicofin\Resources\RequisitoTipoFinanziamentos\Schemas;

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
                    ->numeric(),
                TextInput::make('tipoprodotto_sub_id')
                    ->numeric(),
                TextInput::make('pratica_requisito_id')
                    ->required()
                    ->numeric(),
                Toggle::make('obbligatorio')
                    ->required(),
                TextInput::make('ordine')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
