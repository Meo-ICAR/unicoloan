<?php

namespace App\Filament\Resources\Tipoprodottos\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TipoprodottoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required(),
                TextInput::make('code')
                    ->label('Codice'),
                Toggle::make('is_external')
                    ->label('Esterno'),
                Toggle::make('is_oneclient')
                    ->label('Prodotto Mono-Cliente'),
                TextInput::make('oam')
                    ->label('Codice OAM'),
                Select::make('tipo_provvigioni')
                    ->label('Tipo Provvigione')
                    ->options(['Lordo' => 'Lordo', 'Erogato' => 'Erogato', 'Netto' => 'Netto']),
            ]);
    }
}
