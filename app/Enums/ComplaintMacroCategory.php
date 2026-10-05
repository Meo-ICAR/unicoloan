<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ComplaintMacroCategory: string implements HasLabel
{
    case Financial = 'financial';
    case Privacy = 'privacy';
    case Insurance = 'insurance';
    case Operational = 'operational';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Financial => 'Intermediazione Creditizia / Finanziario',
            self::Privacy => 'Privacy & GDPR',
            self::Insurance => 'Comparto Assicurativo (IVASS)',
            self::Operational => 'Operativo / Servizi Generali',
        };
    }
}
