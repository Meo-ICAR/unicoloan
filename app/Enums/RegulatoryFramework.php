<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RegulatoryFramework: string implements HasLabel
{
    case IVASS  = 'ivass';
    case OAM    = 'oam';
    case GDPR   = 'gdpr';
    case SAFETY = 'safety';

    public function getLabel(): string
    {
        return match ($this) {
            self::IVASS  => 'IVASS',
            self::OAM    => 'OAM',
            self::GDPR   => 'GDPR',
            self::SAFETY => 'Sicurezza sul Lavoro',
        };
    }
}
