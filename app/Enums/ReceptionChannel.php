<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ReceptionChannel: string implements HasLabel
{
    case Pec = 'pec';
    case Raccomandata = 'raccomandata';
    case Email = 'email';
    case BreviManu = 'brevi_manu';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pec => 'Posta Elettronica Certificata (PEC)',
            self::Raccomandata => 'Raccomandata A/R',
            self::Email => 'Email Ordinaria',
            self::BreviManu => 'Consegna a Mano (Brevi Manu)',
        };
    }
}
