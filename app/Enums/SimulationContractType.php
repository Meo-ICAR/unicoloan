<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SimulationContractType: string implements HasLabel
{
    case Cessione = 'cessione';
    case Delega = 'delega';
    case Prestito = 'prestito';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cessione => 'Cessione del quinto',
            self::Delega => 'Delegazione di pagamento',
            self::Prestito => 'Prestito personale',
        };
    }

    /**
     * La delegazione di pagamento passa dal datore di lavoro: non e' disponibile ai pensionati.
     */
    public function allowsRetirees(): bool
    {
        return $this !== self::Delega;
    }
}
