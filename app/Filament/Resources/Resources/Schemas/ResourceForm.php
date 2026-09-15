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
                    ->required(),

                TextInput::make('name')
                    ->required(),
                TextInput::make('group'),
                Select::make('min_plan')
                    ->options(['BASE' => 'Base', 'MEDIUM' => 'Medium', 'FULL' => 'Full'])
                    ->default('BASE')
                    ->required(),
            ]);
    }
}
