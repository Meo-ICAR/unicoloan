<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PraticaClientRole: string implements HasColor, HasLabel
{
    case Applicant = 'richiedente';
    case CoObligor = 'coobbligato';
    case Guarantor = 'garante';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Applicant => 'Richiedente',
            self::CoObligor => 'Coobbligato',
            self::Guarantor => 'Garante',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Applicant => 'primary',
            self::CoObligor => 'warning',
            self::Guarantor => 'info',
        };
    }
}
