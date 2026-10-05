<?php

namespace App\Filament\Resources\MailAccounts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class MailAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('email_address')
                    ->label('Indirizzo email')
                    ->searchable(),
                TextColumn::make('email_reply_to')
                    ->label('Indirizzo email di risposta')
                    ->searchable(),
                IconColumn::make('is_pec')
                    ->label('PEC')
                    ->boolean(),
                TextColumn::make('incoming_protocol')
                    ->label('Protocollo in entrata')
                    ->badge(),
                TextColumn::make('incoming_host')
                    ->label('Host in entrata')
                    ->searchable(),
                TextColumn::make('incoming_port')
                    ->label('Porta in entrata')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('incoming_username')
                    ->label('Username in entrata')
                    ->searchable(),
                TextColumn::make('incoming_encryption')
                    ->label('Crittografia in entrata')
                    ->badge(),
                TextColumn::make('smtp_host')
                    ->label('Host SMTP')
                    ->searchable(),
                TextColumn::make('smtp_port')
                    ->label('Porta SMTP')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('smtp_username')
                    ->label('Username SMTP')
                    ->searchable(),
                TextColumn::make('smtp_encryption')
                    ->label('Crittografia SMTP')
                    ->badge(),
                IconColumn::make('is_active')
                    ->label('Attivo')
                    ->boolean(),
                TextColumn::make('mailable_type')
                    ->label('Tipo entità collegata')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('mailable_id')
                    ->label('ID entità collegata')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make()
                    ->label('Eliminati'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
