<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required(),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required(),
                Select::make('role')
                    ->label('Ruolo')
                    ->options([
                        'user' => 'Utente',
                        'quality' => 'Qualita',
                        'inspector' => ' Audit / Compilance ',
                        'sos' => 'Segn. SOS',
                        'admin' => 'Amministratore',
                    ])
                    ->required(),
                TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->required(),

            ]);
    }
}
