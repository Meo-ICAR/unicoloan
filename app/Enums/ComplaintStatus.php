<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ComplaintStatus: string implements HasLabel, HasColor
{
    case Open = 'open';
    case Investigating = 'investigating';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Escalated = 'escalated';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Open => 'Aperto / Ricevuto',
            self::Investigating => 'In Istruttoria',
            self::Accepted => 'Accolto (Chiuso)',
            self::Rejected => 'Respinto (Chiuso)',
            self::Escalated => 'In Escalation (ABF/Autorità)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Open => 'danger',
            self::Investigating => 'warning',
            self::Accepted => 'success',
            self::Rejected => 'gray',
            self::Escalated => 'info',
        };
    }
}
