<?php

namespace App\Enums;

use App\Models\Pratica;
use Filament\Support\Contracts\HasLabel;

/**
 * Famiglia di finanziamento della pratica, dedotta dal macro prodotto (`tipo_prodotto`)
 * o, in mancanza, dal prodotto (`denominazione_prodotto`). I raggruppamenti seguono
 * le famiglie di `PlichiPraticaSeeder`.
 */
enum FinancingType: string implements HasLabel
{
    case Cessione = 'cessione';
    case Delega = 'delega';
    case Tfs = 'tfs';
    case Prestito = 'prestito';
    case Mutuo = 'mutuo';
    case Impresa = 'impresa';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cessione => 'Cessione del quinto',
            self::Delega => 'Delega di pagamento',
            self::Tfs => 'Anticipo TFS',
            self::Prestito => 'Prestito personale',
            self::Mutuo => 'Mutuo',
            self::Impresa => 'Finanziamento alle imprese',
        };
    }

    /**
     * Sezioni e campi pertinenti al tipo: le sezioni non elencate qui restano sempre visibili,
     * quelle elencate in `FinancingSection` si vedono solo per i tipi che le includono.
     *
     * @return list<FinancingSection>
     */
    public function sections(): array
    {
        return match ($this) {
            self::Cessione, self::Delega => [
                FinancingSection::PraticaNomeCliente,
                FinancingSection::PraticaRateizzazione,
                FinancingSection::ClienteDatiNascita,
                FinancingSection::ClienteDatoreLavoro,
                FinancingSection::ClienteReddito,
                FinancingSection::ClienteQuotaCedibile,
                FinancingSection::ClienteImpegniTerzi,
            ],
            self::Tfs => [
                FinancingSection::PraticaNomeCliente,
                FinancingSection::PraticaSoggettiCollegati,
                FinancingSection::ClienteDatiNascita,
                FinancingSection::ClienteDatoreLavoro,
                FinancingSection::ClienteReddito,
            ],
            self::Prestito => [
                FinancingSection::PraticaNomeCliente,
                FinancingSection::PraticaRateizzazione,
                FinancingSection::PraticaSoggettiCollegati,
                FinancingSection::ClienteDatiNascita,
                FinancingSection::ClienteDatoreLavoro,
                FinancingSection::ClienteReddito,
                FinancingSection::ClienteImpegniTerzi,
            ],
            self::Mutuo => [
                FinancingSection::PraticaNomeCliente,
                FinancingSection::PraticaRateizzazione,
                FinancingSection::PraticaSoggettiCollegati,
                FinancingSection::PraticaDatiImmobile,
                FinancingSection::ClienteDatiNascita,
                FinancingSection::ClienteDatoreLavoro,
                FinancingSection::ClienteReddito,
                FinancingSection::ClienteImpegniTerzi,
            ],
            self::Impresa => [
                FinancingSection::PraticaRateizzazione,
                FinancingSection::PraticaSoggettiCollegati,
                FinancingSection::ClienteDatiSocietari,
                FinancingSection::ClienteCaricheSociali,
                FinancingSection::ClienteSediSiti,
                FinancingSection::ClienteImpegniTerzi,
            ],
        };
    }

    public function shows(FinancingSection $section): bool
    {
        return in_array($section, $this->sections(), true);
    }

    /**
     * L'ordine conta: "Prestito Aziendale" e' un finanziamento alle imprese, non un prestito personale.
     */
    public static function fromProduct(?string $product): ?self
    {
        $product = trim((string) $product);

        if ($product === '') {
            return null;
        }

        return match (true) {
            (bool) preg_match('/aziend|impres|leasing/i', $product) => self::Impresa,
            (bool) preg_match('/\btfs\b|\btfr\b|fine servizio/i', $product) => self::Tfs,
            (bool) preg_match('/cession|quinto/i', $product) => self::Cessione,
            (bool) preg_match('/deleg/i', $product) => self::Delega,
            (bool) preg_match('/mutu|ipotec/i', $product) => self::Mutuo,
            (bool) preg_match('/prestit|chirograf|microcredit/i', $product) => self::Prestito,
            default => null,
        };
    }

    public static function forPratica(Pratica $pratica): ?self
    {
        return self::fromProduct($pratica->tipo_prodotto) ?? self::fromProduct($pratica->denominazione_prodotto);
    }
}
