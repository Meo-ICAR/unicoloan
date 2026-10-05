<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FindingSeverity: string implements HasLabel, HasColor
{
    case Observation = 'observation';
    case Minor = 'minor';
    case Major = 'major';
    case Critical = 'critical';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Observation => 'Osservazione',
            self::Minor => 'Rilievo Minore',
            self::Major => 'Rilievo Maggiore',
            self::Critical => 'Critico / Bloccante',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Observation => 'gray',
            self::Minor => 'info',
            self::Major => 'warning',
            self::Critical => 'danger',
        };
    }
}
