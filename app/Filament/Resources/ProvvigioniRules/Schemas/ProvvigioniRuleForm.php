<?php

namespace App\Filament\Unicofin\Resources\ProvvigioniRules\Schemas;

use App\Models\Cliente;
use App\Models\Fornitore;
use App\Models\FornitoriRole;
use App\Models\TipoprodottoSub;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProvvigioniRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Toggle::make('coordinamento')
                    ->required(),
                Toggle::make('iscliente')
                    ->required(),
                TextInput::make('tipo_provvigioni')
                    ->required()
                    ->default('lordo'),
                TextInput::make('value')
                    ->numeric()
                    ->default(0.0),
                DatePicker::make('valid_from'),
                DatePicker::make('valid_to'),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
