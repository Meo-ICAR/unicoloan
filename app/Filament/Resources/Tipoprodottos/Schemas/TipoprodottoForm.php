<?php

namespace App\Filament\Unicofin\Resources\Tipoprodottos\Schemas;

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
                    ->required(),
                TextInput::make('code'),
                Toggle::make('is_external'),
                Toggle::make('is_oneclient'),
                TextInput::make('oam'),
                Select::make('tipo_provvigioni')
                    ->options(['Lordo' => 'Lordo', 'Erogato' => 'Erogato', 'Netto' => 'Netto']),
            ]);
    }
}
