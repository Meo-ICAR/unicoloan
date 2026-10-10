<?php

namespace App\Services\Kyc;

use Unico\Core\Enums\DocumentStatus;
use App\Models\Client;
use App\Models\Document;
use App\Models\KycQuestionnaire;
use App\Models\PdfModule;
use App\Models\User;
use App\Services\ModuleDataResolver;
use App\Services\PdfFormException;
use App\Services\PdfFormFiller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Compila il PDF del QAV e lo archivia come Document del cliente.
 */
class KycQavGenerator
{
    public const SLUG_PERSON = 'qav-persona-fisica';

    public const SLUG_COMPANY = 'qav-persona-giuridica';

    public function __construct(
        private ModuleDataResolver $resolver,
        private PdfFormFiller $filler,
    ) {}

    public function moduleFor(Client $client): ?PdfModule
    {
        $slug = $client->is_person ? self::SLUG_PERSON : self::SLUG_COMPANY;

        return PdfModule::query()->active()
            ->whereHas('documentType', fn ($query) => $query->where('slug', $slug))
            ->first();
    }

    /**
     * PDF compilato, senza salvare nulla.
     *
     * @throws \DomainException
     * @throws PdfFormException
     */
    public function render(KycQuestionnaire $questionnaire): string
    {
        return $this->renderWith($questionnaire, $this->requireModule($questionnaire->client));
    }

    /**
     * Compila il PDF e lo archivia: il rendering avviene prima della transazione.
     *
     * @throws \DomainException
     * @throws PdfFormException
     */
    public function generate(KycQuestionnaire $questionnaire, ?User $user): Document
    {
        $content = $this->render($questionnaire);

        return DB::connection('mysql')->transaction(fn (): Document => $this->store($questionnaire, $content, $user));
    }

    /**
     * Salva Document, media e activity per un PDF gia' compilato. Da chiamare dentro una transazione.
     *
     * @throws \DomainException
     */
    public function store(KycQuestionnaire $questionnaire, string $content, ?User $user): Document
    {
        $client = $questionnaire->client;
        $module = $this->requireModule($client);

        /** @var Document $document */
        $document = $client->documents()->create([
            'document_type_id' => $module->document_type_id,
            'name' => $module->name.' - '.trim(($client->name ?? '').' '.($client->first_name ?? '')),
            'status' => DocumentStatus::UPLOADED->value,
            'spatie_collection' => 'documents',
            'emitted_at' => today(),
            'uploaded_by' => $user?->getKey(),
            'created_by' => $user?->getKey(),
        ]);

        activity('kyc_qav')
            ->performedOn($client)
            ->causedBy($user)
            ->event('qav_generato')
            ->withProperties(['questionnaire_id' => $questionnaire->getKey(), 'document_id' => $document->getKey()])
            ->log('QAV generato per il cliente');

        // Il file va allegato per ultimo: un errore precedente non deve lasciare file orfani sul disco.
        $document->addMediaFromString($content)
            ->usingFileName(Str::slug($module->name.' '.$client->tax_code).'.pdf')
            ->toMediaCollection('documents');

        return $document;
    }

    private function requireModule(Client $client): PdfModule
    {
        return $this->moduleFor($client)
            ?? throw new \DomainException('Modulo QAV non configurato per il tipo di cliente.');
    }

    private function renderWith(KycQuestionnaire $questionnaire, PdfModule $module): string
    {
        return $this->filler->fill(
            $module->loadMissing('fields'),
            $this->resolver->resolveForClient($questionnaire->client, $questionnaire),
        );
    }
}
