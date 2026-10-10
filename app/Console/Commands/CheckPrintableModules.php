<?php

namespace App\Console\Commands;

use App\Models\PdfModuleField;
use App\Models\PROFORMA\Pratica;
use App\Services\ModuleDataResolver;
use App\Services\ModuleSuggester;
use Unico\Core\Pdf\PdfFormException;
use Unico\Core\Pdf\PdfFormFiller;
use Illuminate\Console\Command;

class CheckPrintableModules extends Command
{
    protected $signature = 'modules:check-printable
        {--pratica=* : Limita ai codici pratica indicati}
        {--limit=0 : Controlla solo le prime N pratiche}
        {--render : Compila davvero ogni PDF con pdftk (lento)}';

    protected $description = 'Verifica che per ogni pratica tutti i moduli suggeriti si possano compilare senza dati mancanti';

    public function handle(ModuleDataResolver $resolver, ModuleSuggester $suggester, PdfFormFiller $filler): int
    {
        $query = Pratica::query()->where('is_notowned', false)->orderBy('codice_pratica');

        if ($this->option('pratica') !== []) {
            $query->whereIn('codice_pratica', $this->option('pratica'));
        }

        if ((int) $this->option('limit') > 0) {
            $query->limit((int) $this->option('limit'));
        }

        $checked = 0;
        $printable = 0;
        $withoutClient = [];
        $withoutModules = [];
        $missing = [];
        $failures = [];

        $query->each(function (Pratica $pratica) use (&$checked, &$printable, &$withoutClient, &$withoutModules, &$missing, &$failures, $resolver, $suggester, $filler): void {
            $checked++;
            $client = $resolver->findClient($pratica);

            if ($client === null) {
                $withoutClient[] = $pratica->codice_pratica;

                return;
            }

            $modules = $suggester->suggest($pratica, $client);

            if ($modules->isEmpty()) {
                $withoutModules[] = $pratica->codice_pratica.' ('.$pratica->tipo_prodotto.')';

                return;
            }

            $data = $resolver->resolve($pratica, $client);
            $feasible = true;

            foreach ($modules as $module) {
                $module->loadMissing('pdfModuleFields');

                $keys = $module->pdfModuleFields->flatMap(fn (PdfModuleField $field) => $field->referencedKeys())->all();

                foreach ($data->missingAmong($keys) as $key) {
                    $missing[$module->name][$key][] = $pratica->codice_pratica;
                    $feasible = false;
                }

                if ($this->option('render')) {
                    try {
                        $filler->fill($module, $data);
                    } catch (PdfFormException $exception) {
                        $failures[$module->name][] = $pratica->codice_pratica.': '.$exception->getMessage();
                        $feasible = false;
                    }
                }
            }

            $printable += $feasible ? 1 : 0;
        });

        $this->info("Pratiche controllate: {$checked} | stampabili in modo completo: {$printable}");

        $this->report('Pratiche senza cliente (codice fiscale/P.IVA non trovato)', $withoutClient);
        $this->report('Pratiche senza moduli suggeriti', $withoutModules);

        foreach ($missing as $module => $keys) {
            $this->warn("Modulo \"{$module}\": dati mancanti");

            foreach ($keys as $key => $codes) {
                $this->line(sprintf('  %s: %d pratiche (es. %s)', $key, count($codes), implode(', ', array_slice($codes, 0, 3))));
            }
        }

        foreach ($failures as $module => $errors) {
            $this->error("Modulo \"{$module}\": compilazione fallita su ".count($errors).' pratiche (es. '.$errors[0].')');
        }

        $hasGaps = $withoutClient !== [] || $withoutModules !== [] || $missing !== [] || $failures !== [];

        return $hasGaps ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $items
     */
    private function report(string $title, array $items): void
    {
        if ($items === []) {
            return;
        }

        $this->warn(sprintf('%s: %d (es. %s)', $title, count($items), implode(', ', array_slice($items, 0, 5))));
    }
}
