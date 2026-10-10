<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Anomalie di un documento caricato: rilevate dai controlli automatici o indicate dall'operatore al rifiuto.
 */
enum DocumentAnomaly: string implements HasLabel
{
    case MissingFile = 'file_mancante';
    case Unreadable = 'file_illeggibile';
    case SignatureMissing = 'firma_mancante';
    case SignatureInvalid = 'firma_non_valida';
    case Expired = 'scaduto';
    case Duplicate = 'duplicato';
    case NonConforming = 'non_conforme';
    case Other = 'altro';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::MissingFile => 'File mancante',
            self::Unreadable => 'File illeggibile',
            self::SignatureMissing => 'Firma mancante',
            self::SignatureInvalid => 'Firma non valida',
            self::Expired => 'Documento scaduto',
            self::Duplicate => 'Duplicato',
            self::NonConforming => 'Documento non conforme',
            self::Other => 'Altra anomalia',
        };
    }
}
