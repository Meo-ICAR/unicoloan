<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Sezioni e campi delle schede pratica e cliente che dipendono dal tipo di finanziamento.
 * La mappa tipo => sezioni e' in `FinancingType::sections()`.
 */
enum FinancingSection: string implements HasLabel
{
    case PraticaNomeCliente = 'pratica_nome_cliente';
    case PraticaRateizzazione = 'pratica_rateizzazione';
    case PraticaSoggettiCollegati = 'pratica_soggetti_collegati';
    case ClienteDatiNascita = 'cliente_dati_nascita';
    case ClienteDatoreLavoro = 'cliente_datore_lavoro';
    case ClienteReddito = 'cliente_reddito';
    case ClienteQuotaCedibile = 'cliente_quota_cedibile';
    case ClienteDatiSocietari = 'cliente_dati_societari';
    case ClienteCaricheSociali = 'cliente_cariche_sociali';
    case ClienteSediSiti = 'cliente_sedi_siti';
    case ClienteImpegniTerzi = 'cliente_impegni_terzi';

    public function getLabel(): string
    {
        return match ($this) {
            self::PraticaNomeCliente => 'Pratica: nome del cliente persona fisica',
            self::PraticaRateizzazione => 'Pratica: rata mensile e numero rate',
            self::PraticaSoggettiCollegati => 'Pratica: richiedente, coobbligati e garanti',
            self::ClienteDatiNascita => 'Cliente: data e luogo di nascita, sesso, cittadinanza',
            self::ClienteDatoreLavoro => 'Cliente: datore di lavoro / ente',
            self::ClienteReddito => 'Cliente: reddito netto mensile (stipendio o pensione)',
            self::ClienteQuotaCedibile => 'Cliente: quota cedibile',
            self::ClienteDatiSocietari => 'Cliente: dati societari, legale rappresentante, ultimo bilancio',
            self::ClienteCaricheSociali => 'Cliente: cariche sociali',
            self::ClienteSediSiti => 'Cliente: sedi e siti web',
            self::ClienteImpegniTerzi => 'Cliente: impegni di terzi (finanziamenti in corso)',
        };
    }

    /**
     * Senza tipo di finanziamento (pratica senza prodotto riconosciuto o cliente aperto
     * fuori dal contesto di una pratica) si vede tutto.
     */
    public function isVisibleFor(?FinancingType $type): bool
    {
        return $type === null || $type->shows($this);
    }
}
