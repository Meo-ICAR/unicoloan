<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SignerRole: string implements HasLabel
{
    case Client = 'client';
    case Collaborator = 'collaborator';

    public function getLabel(): string
    {
        return match ($this) {
            self::Client => 'Cliente',
            self::Collaborator => 'Collaboratore',
        };
    }
}
