<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum GdprDsrStatus: string implements HasLabel
{
    case PENDING   = 'pending';
    case EXTENDED  = 'extended';
    case FULFILLED = 'fulfilled';
    case REJECTED  = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING   => 'In Attesa',
            self::EXTENDED  => 'Esteso',
            self::FULFILLED => 'Evaso',
            self::REJECTED  => 'Rifiutato',
        };
    }
}
