<?php

namespace App\Console\Commands;

use App\Services\PdfFieldSynchronizer;
use Unico\Core\Pdf\PdfFormException;
use Unico\Core\Pdf\PdfFormFiller;
use Illuminate\Console\Command;

class SyncPdfModuleFields extends Command
{
    protected $signature = 'modules:sync-fields {--diagnostic : Genera per ogni modulo un PDF in cui ogni campo mostra il proprio nome}
                            {--link-document-types : Collega ogni modulo a un tipo documento del catalogo, creandolo se manca}';

    protected $description = 'Legge i campi AcroForm dei PDF in storage/app/public/module e li registra in pdf_module_fields';

    public function handle(PdfFieldSynchronizer $synchronizer, PdfFormFiller $filler): int
    {
        $files = $synchronizer->discoverFiles();

        if ($files === []) {
            $this->warn('Nessun PDF trovato in storage/app/public/'.PdfFieldSynchronizer::MODULE_DIRECTORY);

            return self::SUCCESS;
        }

        $failed = false;

        foreach ($files as $file) {
            try {
                $result = $synchronizer->sync($file);
            } catch (PdfFormException $exception) {
                $failed = true;
                $this->error(basename($file).': '.$exception->getMessage());

                continue;
            }

            $module = $result['module'];

            $this->line(sprintf(
                '%s: %d nuovi, %d non piu\' presenti nel PDF',
                $module->name,
                count($result['created']),
                count($result['removed']),
            ));

            if ($result['removed'] !== []) {
                $this->warn('  campi spariti: '.implode(', ', $result['removed']));
            }

            if ($this->option('link-document-types')) {
                $this->line('  tipo documento: '.$synchronizer->linkDocumentType($module)->name);
            }

            if ($this->option('diagnostic')) {
                $this->line('  diagnostico: storage/app/public/'.$filler->storeDiagnostic($module->load('pdfModuleFields')));
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
