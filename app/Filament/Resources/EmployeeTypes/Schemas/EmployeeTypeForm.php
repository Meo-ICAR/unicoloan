<?php

namespace App\Filament\Resources\EmployeeTypes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class EmployeeTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome'),
                TextInput::make('icon')
                    ->label('Icona'),
                TextInput::make('companytype')
                    ->label('Tipo azienda'),
                Toggle::make('is_external')
                    ->label('Esterno'),
            ]);
    }
}
