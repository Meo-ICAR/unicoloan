<?php

namespace App\Filament\Resources\EmployeeTypes\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class EmployeeTypeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')
                    ->label('Nome')
                    ->placeholder('-'),
                TextEntry::make('icon')
                    ->label('Icona')
                    ->placeholder('-'),
                TextEntry::make('companytype')
                    ->label('Tipo azienda')
                    ->placeholder('-'),
                IconEntry::make('is_external')
                    ->label('Esterno')
                    ->boolean()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->label('Creato il')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->label('Aggiornato il')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
