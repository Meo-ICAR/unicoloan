<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AgentAccessOutcome: string implements HasLabel
{
    case Created = 'created';
    case AlreadyEnabled = 'already_enabled';
    case MissingEmail = 'missing_email';
    case EmailInUse = 'email_in_use';

    public function getLabel(): string
    {
        return match ($this) {
            self::Created => 'Accesso creato',
            self::AlreadyEnabled => 'Gia\' abilitato',
            self::MissingEmail => 'Email mancante o non valida',
            self::EmailInUse => 'Email gia\' usata da un altro utente',
        };
    }
}
