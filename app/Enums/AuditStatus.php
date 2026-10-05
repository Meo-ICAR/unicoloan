<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AuditStatus: string implements HasLabel, HasColor
{
    case PLANNED = 'planned';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case FOLLOW_UP = 'follow_up';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PLANNED => 'Pianificato',
            self::IN_PROGRESS => 'In Corso',
            self::COMPLETED => 'Completato',
            self::CANCELLED => 'Annullato',
            self::FOLLOW_UP => 'Follow-up',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PLANNED => 'info',
            self::IN_PROGRESS => 'primary',
            self::COMPLETED => 'success',
            self::CANCELLED => 'danger',
            self::FOLLOW_UP => 'warning',
        };
    }
}
