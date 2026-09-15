<?php

namespace App\Filament\Resources\Organizations\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class OrganizationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('acronym')
                    ->label('Sigla')
                    ->required(),
                TextInput::make('name')
                    ->label('Denominazione')
                    ->required(),
                Textarea::make('description')
                    ->label('Descrizione')
                    ->columnSpanFull(),
                TextInput::make('reference_law')
                    ->label('Norma di riferimento'),
                TextInput::make('website')
                    ->label('Sito web')
                    ->url(),
                TextInput::make('pec_email')
                    ->label('Email PEC')
                    ->email(),
                Toggle::make('is_active')
                    ->label('Attivo')
                    ->required(),
            ]);
    }
}
