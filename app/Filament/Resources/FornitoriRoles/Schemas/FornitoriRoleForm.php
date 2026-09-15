<?php

namespace App\Filament\Resources\FornitoriRoles\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FornitoriRoleForm
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
                TextInput::make('level')
                    ->label('Livello')
                    ->numeric()
                    ->default(1),
                TextInput::make('description')
                    ->label('Descrizione'),
            ]);
    }
}
