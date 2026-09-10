<?php

namespace App\ValueObjects;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Rappresenta un semestre OAM (gennaio-giugno oppure luglio-dicembre) e i suoi
 * estremi temporali. Immutabile: i metodi di modifica restituiscono una nuova
 * istanza invece di mutare lo stato.
 */
class OamSemester
{
    public readonly CarbonImmutable $start;

    public readonly CarbonImmutable $end;

    public function __construct(
        public readonly int $year,
        public readonly int $semesterNumber,
    ) {
        if (! in_array($semesterNumber, [1, 2], true)) {
            throw new InvalidArgumentException("Semestre non valido: {$semesterNumber}. Ammessi 1 o 2.");
        }

        if ($semesterNumber === 1) {
            $this->start = CarbonImmutable::create($year, 1, 1, 0, 0, 0);
            $this->end = CarbonImmutable::create($year, 6, 30, 23, 59, 59);
        } else {
            $this->start = CarbonImmutable::create($year, 7, 1, 0, 0, 0);
            $this->end = CarbonImmutable::create($year, 12, 31, 23, 59, 59);
        }
    }

    /**
     * Semestre di riferimento in base alla data odierna.
     *
     * Fino a ottobre incluso si lavora sul 1° semestre dell'anno corrente;
     * a novembre/dicembre si passa al 2° semestre.
     */
    public static function current(?CarbonImmutable $now = null): self
    {
        $now ??= CarbonImmutable::now();

        return $now->month <= 10
            ? new self($now->year, 1)
            : new self($now->year, 2);
    }

    /**
     * Alias storico mantenuto per retrocompatibilita' con il codice esistente.
     */
    public static function getInBaseAlMeseCorrente(): self
    {
        return self::current();
    }

    /**
     * Costruisce il semestre a partire da un periodo in formato "YYYYMM"
     * (es. "202606" -> 1° semestre 2026, "202612" -> 2° semestre 2026).
     */
    public static function fromPeriod(string $period): self
    {
        if (! preg_match('/^(\d{4})(\d{2})$/', $period, $m)) {
            throw new InvalidArgumentException("Periodo non valido: {$period}. Atteso formato YYYYMM.");
        }

        $month = (int) $m[2];

        return new self((int) $m[1], $month <= 6 ? 1 : 2);
    }

    /**
     * Periodo in formato "YYYYMM" usato come chiave nelle tabelle OAM
     * (ultimo mese del semestre: 06 oppure 12).
     */
    public function period(): string
    {
        return sprintf('%04d%02d', $this->year, $this->semesterNumber === 1 ? 6 : 12);
    }

    /**
     * Etichetta leggibile del periodo (es. "01/01/2026 - 30/06/2026").
     */
    public function label(): string
    {
        return $this->start->format('d/m/Y').' - '.$this->end->format('d/m/Y');
    }
}
