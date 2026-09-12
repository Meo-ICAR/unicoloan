<?php

namespace App\Filament\Unicofin\Resources\TipoprodottoSubs\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TipoprodottoSubForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dettagli Sottoprodotto')
                    ->description('Gestisci i dettagli e i vincoli del sottoprodotto finanziario.')
                    ->columnSpanFull()
                    ->schema([
                        // Mostra il nome del padre in sola lettura

                        TextInput::make('name')
                            ->label('Nome Sottoprodotto')
                            ->maxLength(50)
                            ->required(),

                        TextInput::make('code')
                            ->label('Codice')
                            ->maxLength(20)
                            ->extraInputAttributes(['style' => 'text-transform: uppercase;'])
                            ->required(),

                        Textarea::make('vincoli')
                            ->label('Vincoli / Note')
                            // tinytext nel database tiene fino a 255 caratteri
                            ->maxLength(255)
                            ->rows(1),
                        // ->columnSpanFull(),

                    ])->columns(3),

            ]);
    }
}
