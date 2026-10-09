<?php

namespace App\Filament\Resources\PdfModules\Schemas;

use App\Enums\PdfModuleClientScope;
use App\Models\Tipoprodotto;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PdfModuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Modulo')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('file_path')
                            ->label('File')
                            ->disabled()
                            ->dehydrated(false),
                        Select::make('document_type_id')
                            ->label('Tipo documento (catalogo)')
                            ->relationship('documentType', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('I documenti generati ereditano scadenze, firma e conservazione di questo tipo.'),
                        TextInput::make('version')
                            ->label('Versione')
                            ->maxLength(255),
                        Select::make('client_scope')
                            ->label('Ambito cliente')
                            ->options(PdfModuleClientScope::class)
                            ->required(),
                        TagsInput::make('tipi_prodotto')
                            ->label('Tipi di prodotto')
                            ->helperText('Vuoto = pertinente per qualunque prodotto. Usare i nomi dei tipi prodotto della pratica.')
                            ->suggestions(fn (): array => Tipoprodotto::query()->orderBy('name')->pluck('name')->filter()->all()),
                        Toggle::make('is_active')
                            ->label('Attivo')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }
}
