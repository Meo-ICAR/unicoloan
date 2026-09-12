<?php

namespace App\Filament\Unicofin\Resources\Clientis\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AgentiBlacklistatiRelationManager extends RelationManager
{
    protected static string $relationship = 'agentiBlacklistati';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('motivo')
                    ->label('Motivazione del Blocco')
                    ->maxLength(65535)
                    ->columnSpanFull(),
                DatePicker::make('data_inizio')
                    ->label('Data Inizio Blocco')
                    ->default(now()),
                DatePicker::make('data_fine')
                    ->label('Data Fine Blocco (Opzionale)'),
            ]);

    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('ragionesociale')
            ->columns([
                TextColumn::make('ragionesociale') // O 'nome', 'cognome' dell'agente
                    ->label('Agente'),
                TextColumn::make('motivo')
                    ->limit(50),
                TextColumn::make('data_inizio')
                    ->date(),
                TextColumn::make('data_fine')
                    ->date()
                    ->placeholder('Indeterminato'),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->headerActions([

            ])
            ->recordActions([
                EditAction::make(),
                DetachAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withoutGlobalScopes([
                    SoftDeletingScope::class,
                ]));
    }
}
