<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ComplaintCategory: string implements HasLabel
{
    case Delay = 'delay';
    case Behavior = 'behavior';
    case Fraud = 'fraud';
    case Rates = 'rates';
    case GdprAccess = 'gdpr_access';
    case GdprErasure = 'gdpr_erasure';
    case Transparency = 'transparency';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Delay => 'Ritardi nella lavorazione/erogazione',
            self::Behavior => "Comportamento scorretto dell'agente/collaboratore",
            self::Fraud => 'Sospetta frode o falsificazione documentale',
            self::Rates => 'Contestazione tassi / condizioni economiche',
            self::GdprAccess => 'Richiesta di Accesso ai Dati (Art. 15 GDPR)',
            self::GdprErasure => 'Richiesta di Cancellazione / Oblio (Art. 17 GDPR)',
            self::Transparency => 'Mancanza di Trasparenza Informativa',
        };
    }
}
