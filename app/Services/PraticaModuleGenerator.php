<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\ModuleSourceKey;
use App\Models\Client;
use App\Models\Document;
use App\Models\PdfModule;
use App\Models\PdfModuleField;
use App\Models\PROFORMA\Pratica;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Orchestra la generazione dei moduli PDF di una pratica: dati, compilazione,
 * archiviazione come Document della pratica e traccia nell'activity log.
 */
class PraticaModuleGenerator
{
    public function __construct(
        private ModuleDataResolver $resolver,
        private PdfFormFiller $filler,
    ) {}

    /**
     * Chiavi dati senza valore, per ogni modulo (indicizzato per id modulo): serve all'anteprima.
     *
     * @param  iterable<PdfModule>  $modules
     * @return array<int, array<int, ModuleSourceKey>>
     */
    public function missingByModule(Pratica $pratica, Client $client, iterable $modules): array
    {
        $data = $this->resolver->resolve($pratica, $client);
        $missing = [];

        foreach ($modules as $module) {
            $keys = $module->fields
                ->flatMap(fn (PdfModuleField $field) => $field->referencedKeys())
                ->all();

            $missing[$module->getKey()] = $data->missingAmong($keys);
        }

        return $missing;
    }

    /**
     * Genera tutti i PDF prima di salvare qualsiasi cosa: se uno fallisce non resta nulla.
     *
     * @param  iterable<PdfModule>  $modules
     * @return Collection<int, Document>
     *
     * @throws PdfFormException
     */
    public function generate(Pratica $pratica, Client $client, iterable $modules, ?User $user): Collection
    {
        $modules = collect($modules)->values();
        $data = $this->resolver->resolve($pratica, $client);

        $rendered = $modules->map(fn (PdfModule $module) => [
            'module' => $module,
            'content' => $this->filler->fill($module, $data),
        ]);

        $reference = $pratica->codice_pratica ?: (string) $pratica->getKey();

        $documents = DB::transaction(function () use ($rendered, $pratica, $reference, $user): Collection {
            return $rendered->map(fn (array $item) => $this->store($pratica, $item['module'], $item['content'], $reference, $user));
        });

        if ($documents->isNotEmpty()) {
            activity('moduli_pdf')
                ->performedOn($client)
                ->causedBy($user)
                ->event('moduli_generati')
                ->withProperties([
                    'pratica_id' => $pratica->getKey(),
                    'codice_pratica' => $pratica->codice_pratica,
                    'moduli' => $modules->pluck('name')->all(),
                    'documenti' => $documents->map(fn (Document $document) => $document->getKey())->all(),
                ])
                ->log('Moduli PDF generati per la pratica '.$reference);
        }

        return $documents;
    }

    private function store(Pratica $pratica, PdfModule $module, string $content, string $reference, ?User $user): Document
    {
        /** @var Document $document */
        $document = $pratica->documents()->create([
            'document_type_id' => $module->document_type_id,
            'name' => $module->name.' - '.$reference,
            'status' => DocumentStatus::UPLOADED->value,
            'spatie_collection' => 'documents',
            'uploaded_by' => $user?->getKey(),
            'created_by' => $user?->getKey(),
        ]);

        $document->addMediaFromString($content)
            ->usingFileName(Str::slug($module->name.' '.$reference).'.pdf')
            ->toMediaCollection('documents');

        return $document;
    }
}
