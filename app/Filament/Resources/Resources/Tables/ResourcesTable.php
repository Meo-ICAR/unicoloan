<?php

namespace App\Filament\Resources\Resources\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ResourcesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('app_name')
                    ->label('Applicazione')
                    ->searchable(),

                TextColumn::make('name')
                    ->label('Nome modulo')
                    ->searchable(),
                TextColumn::make('group')
                    ->label('Gruppo')
                    ->searchable(),
                TextColumn::make('min_plan')
                    ->label('Piano minimo')
                    ->badge(),

            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
