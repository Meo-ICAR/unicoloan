<?php

namespace App\Filament\Resources\PraticaRequisitos\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PraticaRequisitoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('codice')
                    ->label('Codice')
                    ->required(),
                TextInput::make('name')
                    ->label('Nome')
                    ->required(),
                Textarea::make('descrizione')
                    ->label('Descrizione')
                    ->columnSpanFull(),
            ]);
    }
}
