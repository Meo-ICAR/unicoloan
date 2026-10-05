<?php

namespace App\Filament\Resources\EmailTemplates\Schemas;

use Filament\Facades\Filament;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class EmailTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Codice')
                    ->required(),
                TextInput::make('name')
                    ->label('Nome')
                    ->required(),
                TextInput::make('subject')
                    ->label('Oggetto')
                    ->required(),
                RichEditor::make('body')
                    ->label('Corpo email')
                    // ->required()
                    ->columnSpanFull(),
                TextInput::make('placeholders')
                    ->label('Segnaposto disponibili'),
                Toggle::make('is_active')
                    ->label('Attivo')
                    ->required(),
                Select::make('source_app') // Assicurati che il nome mappi la colonna del DB (es. 'source_app')
                    ->label('Applicazione di Origine')

    // 1. Imposta le opzioni selezionabili dall'utente
                    ->options([
                        'UnicoOAM' => 'UnicoOAM (Pannello Amministrazione)',
                        'UnicoFin' => 'UnicoFin (Pannello Finanziario)',
                    ])

    // 2. Imposta il valore di default dinamico in base al panel corrente
                    ->default(function () {
                        $currentPanelId = Filament::getCurrentPanel()?->getId();

                        return ($currentPanelId === 'admin') ? 'UnicoOAM' : 'UnicoFin';
                    }),
            ]);
    }
}
