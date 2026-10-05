<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum DocumentStatus: string implements HasColor, HasIcon, HasLabel
{
    case NA = 'na';
    case PENDING = 'richiesto';
    case UPLOADED = 'caricato';
    case APPROVED = 'approvato';
    case REJECTED = 'respinto';
    case PROVISIONAL = 'provvisorio';
    case EXPIRED = 'scaduto';
    case NOREADABLE = 'illegibile';
    case REVOKED = 'revocato';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::NA => 'N/A',
            self::PENDING => 'Richiesto',
            self::UPLOADED => 'Caricato',
            self::APPROVED => 'Approvato',
            self::NOREADABLE => 'Illegibile',
            self::REJECTED => 'Respinto',
            self::PROVISIONAL => 'Provvisorio',
            self::EXPIRED => 'Scaduto',
            self::REVOKED => 'Revocato'
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::NA => 'gray',
            self::PENDING => 'info',
            self::UPLOADED => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
            self::PROVISIONAL => 'warning',
            self::EXPIRED => 'danger',
            self::NOREADABLE => 'danger',
            self::REVOKED => 'gray'
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::NA => 'heroicon-m-minus-circle',
            self::REQUESTED => 'heroicon-m-question-mark-circle',
            self::UPLOADED => 'heroicon-m-arrow-up-tray',
            self::APPROVED => 'heroicon-m-check-badge',
            self::REJECTED => 'heroicon-m-x-circle',
            self::PROVISIONAL => 'heroicon-m-clock',
            self::EXPIRED => 'heroicon-m-exclamation-triangle',
            self::NOREADABLE => 'heroicon-m-x-circle',
            self::REVOKED => 'heroicon-m-x-circle',

        };
    }
}
