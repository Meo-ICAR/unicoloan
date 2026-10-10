<?php

namespace App\Filament\Resources\Tipoprodottos\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TipoprodottoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required(),
                TextInput::make('code')
                    ->label('Codice'),
                Toggle::make('is_external')
                    ->label('Esterno'),
                Toggle::make('is_oneclient')
                    ->label('Prodotto Mono-Cliente'),
                Toggle::make('for_person')
                    ->label('Per persona fisica')
                    ->default(true),
                Toggle::make('for_company')
                    ->label('Per persona giuridica')
                    ->default(true),
                Toggle::make('requires_mandate')
                    ->label('Richiede il mandato')
                    ->helperText('Disattivare per i prodotti senza mandato, ad esempio le utenze.')
                    ->default(true),
                Toggle::make('is_third_party')
                    ->label('Finanziamento / impegno di terzi')
                    ->helperText('Impegni del cliente presso altri (cassa mutua, pignoramento, assegni di mantenimento...): non sono pratiche di lavorazione.')
                    ->default(false),
                TextInput::make('oam')
                    ->label('Codice OAM'),
                Select::make('tipo_provvigioni')
                    ->label('Tipo Provvigione')
                    ->options(['Lordo' => 'Lordo', 'Erogato' => 'Erogato', 'Netto' => 'Netto']),
            ]);
    }
}
