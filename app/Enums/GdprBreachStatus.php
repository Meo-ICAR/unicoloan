<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum GdprBreachStatus: string implements HasLabel
{
    case INVESTIGATING = 'investigating';
    case CONTAINED     = 'contained';
    case CLOSED        = 'closed';

    public function getLabel(): string
    {
        return match ($this) {
            self::INVESTIGATING => 'In Investigazione',
            self::CONTAINED     => 'Contenuto',
            self::CLOSED        => 'Chiuso',
        };
    }
}
