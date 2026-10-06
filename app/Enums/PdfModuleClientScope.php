<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PdfModuleClientScope: string implements HasLabel
{
    case PersonaFisica = 'persona_fisica';
    case PersonaGiuridica = 'persona_giuridica';
    case Entrambi = 'entrambi';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PersonaFisica => 'Persona fisica',
            self::PersonaGiuridica => 'Persona giuridica',
            self::Entrambi => 'Entrambi',
        };
    }

    /**
     * Indica se il modulo e' pertinente per il tipo di cliente.
     */
    public function matches(bool $isPerson): bool
    {
        return match ($this) {
            self::PersonaFisica => $isPerson,
            self::PersonaGiuridica => ! $isPerson,
            self::Entrambi => true,
        };
    }
}
