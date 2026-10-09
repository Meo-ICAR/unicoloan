<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum KycExecutorLink: string implements HasLabel
{
    case LegalRepresentative = 'rappresentante_legale';
    case Delegate = 'delegato';

    public function getLabel(): string
    {
        return match ($this) {
            self::LegalRepresentative => 'Rappresentante legale',
            self::Delegate => 'Delegato',
        };
    }
}
