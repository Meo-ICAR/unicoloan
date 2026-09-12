<?php

namespace App\Filament\Unicofin\Resources\PraticaRequisitos\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class PraticaRequisitoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('codice')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                Textarea::make('descrizione')
                    ->columnSpanFull(),
            ]);
    }
}
