<?php

namespace App\Filament\Unicofin\Resources\FornitoriRoles\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FornitoriRoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('code'),
                TextInput::make('level')
                    ->numeric()
                    ->default(1),
                TextInput::make('description'),
            ]);
    }
}
