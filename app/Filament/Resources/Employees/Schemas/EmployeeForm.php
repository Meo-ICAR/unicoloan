<?php

namespace App\Filament\Resources\Employees\Schemas;

use App\Models\EmployeeType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Informazioni Anagrafiche
                Section::make('Informazioni Anagrafiche')
                    ->description('Dati principali del dipendente')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nominativo')
                            ->required()
                            ->maxLength(255),

                        Select::make('employee_roles')
                            ->label('Ruoli')
                            ->multiple()
                            ->options(
                                EmployeeType::query()
                                    ->pluck('name', 'name')
                                    ->map(fn (string $name) => ucfirst($name))
                            )
                            ->searchable()
                            ->nullable(),
                        TextInput::make('email')
                            ->label('Indirizzo email')
                            ->email()
                            ->nullable()
                            //    ->required()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Telefono')
                            ->tel()
                            ->nullable()
                            ->maxLength(20),
                        Toggle::make('is_external')
                            ->label('Collaboratore esterno'),

                    ]),
                // Informazioni Lavorative
                Section::make('Informazioni Lavorative')
                    ->description('Dati di impiego e organizzazione')
                    ->schema([
                        DatePicker::make('hiring_date')
                            ->label('Inizio')
                            ->required(),
                        DatePicker::make('termination_date')
                            ->label('Cessazione')
                            ->nullable(),
                        Select::make('branch_id')
                            ->label('Sede')
                            ->relationship('branch', 'name')
                            ->searchable()
                            ->preload(),
                        //  ->required(),
                        Select::make('coordinated_by_id')
                            ->label('Coordinato da')
                            ->relationship('coordinator', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        // ->helperText('Seleziona un coordinatore della stessa sede'),
                    ]),
                // Dati OAM e RUI
                Section::make('Dati OAM e RUI')
                    ->description('Informazioni per iscrizioni OAM e RUI')
                    ->schema([
                        Toggle::make('numero_iscrizione_rui')
                            ->label('Iscritto OAM')
                            ->reactive(),
                        DatePicker::make('oam_at')
                            ->label('Data Iscrizione OAM')
                            ->nullable()
                            ->visible(fn (callable $get) => $get('numero_iscrizione_rui')),
                        /*
                            TextInput::make('oam')
                            ->label('Numero Iscrizione OAM')
                            ->maxLength(50)
                            ->visible(fn (callable $get) => $get('numero_iscrizione_rui'))
                            ->nullable(),
*/
                        TextInput::make('oam_name')
                            ->label('Nome OAM')
                            ->maxLength(255)
                            ->nullable()
                            ->visible(fn (callable $get) => $get('numero_iscrizione_rui')),
                        DatePicker::make('oam_dismissed_at')
                            ->label('Data Cancellazione OAM')
                            ->nullable()
                            ->visible(fn (callable $get) => $get('numero_iscrizione_rui')),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
