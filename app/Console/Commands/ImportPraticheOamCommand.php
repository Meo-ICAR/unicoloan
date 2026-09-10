<?php

namespace App\Console\Commands;

use App\Services\ImportPraticheService;
use App\ValueObjects\OamSemester;
use Illuminate\Console\Command;
use Throwable;

class ImportPraticheOamCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'oam:import-pratiche
        {--year= : Anno del semestre da importare (default: semestre corrente)}
        {--semester= : Numero del semestre 1 o 2 (default: semestre corrente)}';

    /**
     * @var string
     */
    protected $description = 'Importa le pratiche da PROFORMA nella tabella oam_pratiches per il semestre indicato';

    public function handle(ImportPraticheService $service): int
    {
        $semester = $this->resolveSemester();

        $this->info("Import pratiche OAM per il periodo {$semester->period()} ({$semester->label()})");

        try {
            $imported = $service->import($semester);
        } catch (Throwable $e) {
            $this->error("Import fallito: {$e->getMessage()}");

            report($e);

            return self::FAILURE;
        }

        $this->info("Import completato: {$imported} pratiche.");

        return self::SUCCESS;
    }

    private function resolveSemester(): OamSemester
    {
        $year = $this->option('year');
        $semester = $this->option('semester');

        if ($year === null && $semester === null) {
            return OamSemester::current();
        }

        $current = OamSemester::current();

        return new OamSemester(
            $year !== null ? (int) $year : $current->year,
            $semester !== null ? (int) $semester : $current->semesterNumber,
        );
    }
}
