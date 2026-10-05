<?php

namespace App\Filament\Resources\Fornitores\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class FornitoreForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(4) // Imposta la griglia principale a 4 colonne
                    ->schema([

                        // --- Riga 1 ---
                        TextInput::make('nome')
                            ->label('Ragione Sociale')
                            ->maxLength(255)
                            ->columnSpan(2), // Prende 2 colonne su 4 perché di solito è più lunga

                        TextInput::make('piva')
                            ->label('Partita IVA')
                            ->maxLength(20),

                        TextInput::make('cf')
                            ->label('Cod. Fiscale')
                            ->maxLength(20),

                        // --- Riga 2 ---
                        DatePicker::make('stipulated_at')
                            ->label('Stipula')
                            ->displayFormat('d/m/y'),
                        TextInput::make('name')
                            ->label('Denominazione Istruttoria')
                            ->disabled()
                            ->columnSpan(2), // Prende 2 colonne su 4 perché di solito è più lunga

                        DatePicker::make('oam_at')
                            ->label('Data iscrizione OAM')
                            ->displayFormat('d/m/y'),

                        TextInput::make('ivass')
                            ->label('Codice IVASS')
                            ->maxLength(30),
                        Select::make('ivass_section')
                            ->label('Sezione IVASS')
                            ->options([
                                'A' => 'A',
                                'B' => 'B',
                                'C' => 'C',
                                'D' => 'D',
                                'E' => 'E',
                            ]),

                        // --- Riga 3 ---
                        TextInput::make('email')
                            ->label('Email aziendale o privata se non ancora assegnata')
                            ->email()
                            ->maxLength(255)
                            ->columnSpan(2), // Prende 2 colonne per dare spazio alla label lunga

                        TextInput::make('pec')
                            ->label('PEC (Posta Elettronica Certificata)')
                            ->email()
                            ->maxLength(255)
                            ->columnSpan(1),
                        Select::make('branch_id')
                            ->label('Sede appartenenza')
                            ->relationship('branch', 'name')
                            ->searchable()
                            ->preload(),
                        DatePicker::make('dismissed_at')
                            ->label('Cessazione')
                            ->displayFormat('d/m/y')
                            ->columnSpan(1),
                    ])->columnSpanFull(),
            ]);
    }
}
