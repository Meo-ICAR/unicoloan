<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AmlReportStatus: string implements HasLabel
{
    case DRAFTED   = 'drafted';
    case EVALUATING = 'evaluating';
    case REPORTED  = 'reported';
    case ARCHIVED  = 'archived';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFTED    => 'Bozza',
            self::EVALUATING => 'In Valutazione',
            self::REPORTED   => 'Segnalata UIF',
            self::ARCHIVED   => 'Archiviata',
        };
    }

    /** @deprecated use getLabel() */
    public function label(): string
    {
        return $this->getLabel();
    }
}
