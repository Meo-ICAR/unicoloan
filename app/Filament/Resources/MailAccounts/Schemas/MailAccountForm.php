<?php

namespace App\Filament\Resources\MailAccounts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MailAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome account'),
                TextInput::make('email_address')
                    ->label('Indirizzo email')
                    ->email()
                    ->required(),
                Toggle::make('is_pec')
                    ->label('PEC')
                    ->required(),
                Select::make('incoming_protocol')
                    ->label('Protocollo in entrata')
                    ->options(['pop3' => 'POP3', 'imap' => 'IMAP'])
                    ->default('pop3')
                    ->required(),
                TextInput::make('incoming_host')
                    ->label('Host in entrata')
                    ->required(),
                TextInput::make('incoming_port')
                    ->label('Porta in entrata')
                    ->required()
                    ->numeric(),
                TextInput::make('incoming_username')
                    ->label('Username in entrata')
                    ->required(),
                Textarea::make('incoming_password')
                    ->label('Password in entrata')
                    ->required()
                    ->columnSpanFull(),
                Select::make('incoming_encryption')
                    ->label('Crittografia in entrata')
                    ->options(['none' => 'Nessuna', 'ssl' => 'SSL', 'tls' => 'TLS'])
                    ->default('ssl')
                    ->required(),
                TextInput::make('smtp_host')
                    ->label('Host SMTP')
                    ->required(),
                TextInput::make('smtp_port')
                    ->label('Porta SMTP')
                    ->required()
                    ->numeric(),
                TextInput::make('smtp_username')
                    ->label('Username SMTP')
                    ->required(),
                Textarea::make('smtp_password')
                    ->label('Password SMTP')
                    ->required()
                    ->columnSpanFull(),
                Select::make('smtp_encryption')
                    ->label('Crittografia SMTP')
                    ->options(['none' => 'Nessuna', 'ssl' => 'SSL', 'tls' => 'TLS'])
                    ->default('ssl')
                    ->required(),
                Toggle::make('is_active')
                    ->label('Attivo')
                    ->required(),
                TextInput::make('mailable_type')
                    ->label('Tipo entità collegata'),
                TextInput::make('mailable_id')
                    ->label('ID entità collegata'),
            ]);
    }
}
