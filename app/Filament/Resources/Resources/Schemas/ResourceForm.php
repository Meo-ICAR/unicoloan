<?php

namespace App\Filament\Resources\Resources\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ResourceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('app_name')
                    ->label('Applicazione')
                    ->required(),

                TextInput::make('name')
                    ->label('Nome modulo')
                    ->required(),
                TextInput::make('group')
                    ->label('Gruppo'),
                Select::make('min_plan')
                    ->label('Piano minimo')
                    ->options(['BASE' => 'Base', 'MEDIUM' => 'Medium', 'FULL' => 'Full'])
                    ->default('BASE')
                    ->required(),
            ]);
    }
}
