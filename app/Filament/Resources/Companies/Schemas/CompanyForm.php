<?php

namespace App\Filament\Resources\Companies\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make()
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        Section::make('Dati Azienda')
                            ->collapsed()
                            ->columnSpan(1)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Ragione sociale')
                                    ->required(),
                                Select::make('company_type')
                                    ->label('Tipo società')
                                    ->options([
                                        'mediatore' => 'Mediatore',
                                        'call center' => 'Call center',
                                        'hotel' => 'Hotel',
                                        'sw house' => 'Software house',
                                    ]),
                                TextInput::make('vat_number')
                                    ->label('Partita IVA / Codice fiscale'),
                                TextInput::make('sponsor')
                                    ->label('Gruppo Societario'),
                            ]),

                        /*
                         * TextInput::make('vat_name')
                         *
                         *     ->label('Denominazione su fatture'),
                         */
                        Section::make('Sezione OAM')
                            ->collapsed()
                            ->columnSpan(1)
                            ->schema([
                                TextInput::make('oam')
                                    ->maxLength(30)
                                    ->label('Codice Iscrizione OAM'),
                                TextInput::make('oam_name')
                                    ->maxLength(255)
                                    ->label('Denominazione OAM'),
                                DatePicker::make('oam_at')
                                    ->label('Data Iscrizione OAM'),
                                TextInput::make('numero_iscrizione_rui')
                                    ->maxLength(50)
                                    ->label('Numero Iscrizione RUI'),
                            ]),
                        Section::make('Sezione IVASS')
                            ->columnSpan(1)
                            ->collapsed()
                            ->schema([
                                TextInput::make('ivass')
                                    ->maxLength(30)
                                    ->label('Codice Iscrizione IVASS'),
                                TextInput::make('ivass_name')
                                    ->maxLength(255)
                                    ->label('Denominazione IVASS'),
                                DatePicker::make('ivass_at')
                                    ->label('Data Iscrizione IVASS'),
                                Select::make('ivass_section')
                                    ->options([
                                        'A' => 'Sezione A',
                                        'B' => 'Sezione B',
                                        'C' => 'Sezione C',
                                        'D' => 'Sezione D',
                                        'E' => 'Sezione E',
                                    ])
                                    ->label('Sezione IVASS'),
                            ]),
                    ]),
            ]);
    }
}
