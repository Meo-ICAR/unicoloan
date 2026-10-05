<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FindingStatus: string implements HasLabel, HasColor
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case AcceptedRisk = 'accepted_risk';
    case Closed = 'closed';

    /**
     * Etichette leggibili per l'interfaccia utente (Filament Form & Table).
     */
    public function getLabel(): ?string
    {
        return match ($this) {
            self::Open => 'Aperto',
            self::InProgress => 'In Lavorazione',
            self::Resolved => 'Risolto (In verifica)',
            self::AcceptedRisk => 'Rischio Accettato',
            self::Closed => 'Chiuso Definitivamente',
        };
    }

    /**
     * Colori associati ai badge di Filament.
     */
    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Open => 'danger',  // Rosso: richiede attenzione immediata
            self::InProgress => 'warning',  // Arancione/Ambra: ci stanno lavorando
            self::Resolved => 'info',  // Blu: l'anomalia è rientrata, attende review
            self::AcceptedRisk => 'gray',  // Grigio: il management ha accettato il rischio
            self::Closed => 'success',  // Verde: tutto sanato e archiviato
        };
    }
}
