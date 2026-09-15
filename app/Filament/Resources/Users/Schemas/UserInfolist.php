<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')
                    ->label('Nome'),
                TextEntry::make('email')
                    ->label('Indirizzo email'),
                TextEntry::make('cf')
                    ->label('Codice fiscale')
                    ->placeholder('-'),
                TextEntry::make('email_verified_at')
                    ->label('Email verificata il')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('azure_id')
                    ->label('ID Azure')
                    ->placeholder('-'),
                TextEntry::make('microsoft_id')
                    ->label('ID Microsoft')
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
