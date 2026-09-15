<?php

namespace App\Filament\Resources\Resources\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ResourceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(5)
            ->components([
                TextEntry::make('app_name')
                    ->label('Applicazione'),
                TextEntry::make('key')
                    ->label('Chiave'),
                TextEntry::make('name')
                    ->label('Nome modulo'),
                TextEntry::make('group')
                    ->label('Gruppo')
                    ->placeholder('-'),
                TextEntry::make('min_plan')
                    ->label('Piano minimo')
                    ->badge(),

            ]);
    }
}
