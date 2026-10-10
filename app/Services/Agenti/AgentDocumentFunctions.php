<?php

namespace App\Services\Agenti;

use App\Models\Client;
use App\Models\Document;
use App\Models\PdfModule;
use App\Models\PROFORMA\Pratica;
use App\Models\SignatureRequest;
use App\Services\ModuleDataResolver;
use App\Services\ModuleSuggester;
use App\Services\PdfFormException;
use App\Services\PraticaModuleGenerator;
use App\Services\Signature\Exceptions\SignatureRequestException;
use App\Services\Signature\SignatureRequestService;
use App\Services\Signature\SignerDefaults;
use App\Services\Signature\SignerInput;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Funzioni documentali di unicoloan messe a disposizione delle app esterne (unicoagent) per le pratiche che hanno consegnato:
 * moduli pertinenti, template, modulo compilato con i dati della pratica e firma elettronica con OTP. Sono le stesse funzioni
 * del portale (ModuleSuggester, PraticaModuleGenerator, SignatureRequestService): qui c'è solo il filtro sulla pratica e sui dati.
 */
class AgentDocumentFunctions
{
    public function __construct(
        private AgentRequestIntake $intake,
        private ModuleSuggester $suggester,
        private ModuleDataResolver $resolver,
        private PraticaModuleGenerator $generator,
        private SignatureRequestService $signatures,
    ) {}

    /** @return Collection<int, PdfModule> moduli attivi pertinenti per prodotto e tipo di cliente della pratica */
    public function modules(Pratica $pratica): Collection
    {
        return $this->suggester->suggest($pratica, $this->client($pratica));
    }

    /** @throws AgentIntakeException */
    public function module(Pratica $pratica, int $id): PdfModule
    {
        return $this->modules($pratica)->firstWhere('id', $id)
            ?? throw new AgentIntakeException('modulo_non_trovato', 'Modulo non disponibile per questa pratica.', 404);
    }

    /** @return array<int, string> chiavi dati che il modulo richiede e che la pratica non ha */
    public function missing(Pratica $pratica, PdfModule $module): array
    {
        $missing = $this->generator->missingByModule($pratica, $this->client($pratica), [$module->load('fields')]);

        return array_map(fn ($key) => $key->value, $missing[$module->getKey()] ?? []);
    }

    /** Contenuto del modulo vuoto, così come è nello storage. @throws AgentIntakeException */
    public function template(PdfModule $module): string
    {
        $disk = Storage::disk('public');

        return $disk->exists($module->file_path)
            ? (string) $disk->get($module->file_path)
            : throw new AgentIntakeException('template_non_disponibile', 'Il file del modulo non è disponibile.', 404);
    }

    /**
     * Compila il modulo con i dati della pratica e lo archivia come documento della pratica (un nuovo documento a ogni chiamata).
     *
     * @throws AgentIntakeException
     */
    public function fill(Pratica $pratica, PdfModule $module): Document
    {
        try {
            return $this->generator->generate($pratica, $this->client($pratica), [$module->load('fields')], null)->first();
        } catch (PdfFormException) {
            throw new AgentIntakeException('compilazione_non_riuscita', 'Il modulo non è stato compilato.', 422);
        }
    }

    /** Documento della richiesta (pratica o cliente) con quell'id. @throws AgentIntakeException */
    public function document(Pratica $pratica, string $id): Document
    {
        return $this->intake->documents($pratica)->first(fn (Document $d): bool => (string) $d->getKey() === $id)
            ?? throw new AgentIntakeException('documento_non_trovato', 'Documento non trovato.', 404);
    }

    /** Contenuto del file del documento, o null se non ancora caricato. */
    public function content(Document $document): ?string
    {
        $media = $document->getFirstMedia(Document::COLLECTION);

        return $media === null ? null : (string) stream_get_contents($media->stream());
    }

    public function lastSignature(Document $document): ?SignatureRequest
    {
        return SignatureRequest::query()->where('document_id', $document->getKey())->latest('id')->first();
    }

    /**
     * Chiede la firma con OTP: i firmatari sono quelli proposti dal portale (cliente, agente della pratica); l'app può indicare
     * telefono ed email da usare per ogni riquadro di firma.
     *
     * @param  array<string, array{telefono?: ?string, email?: ?string}>  $contacts  per slot (es. «cliente»)
     *
     * @throws AgentIntakeException
     */
    public function requestSignature(Pratica $pratica, Document $document, array $contacts = []): SignatureRequest
    {
        $defaults = SignerDefaults::for($document);
        $signers = [];

        foreach ($document->documentType?->pdfModule?->signature_slots ?? [] as $slot) {
            $name = $slot['slot'];
            $base = $defaults[$name] ?? null;

            if ($base === null) {
                throw new AgentIntakeException('firmatario_mancante', "Firmatario non determinabile per il riquadro «{$name}».", 422);
            }

            $signers[] = new SignerInput(
                $name, $base->role, $base->firstName, $base->lastName,
                $contacts[$name]['email'] ?? $base->email, $contacts[$name]['telefono'] ?? $base->phone,
                $base->taxCode, $base->signerType, $base->signerRef,
            );
        }

        try {
            return $this->signatures->send($document, $signers, null);
        } catch (SignatureRequestException $e) {
            throw new AgentIntakeException('firma_non_richiesta', $e->getMessage(), 422);
        }
    }

    private function client(Pratica $pratica): Client
    {
        return $this->resolver->findClient($pratica)
            ?? throw new AgentIntakeException('cliente_mancante', 'Cliente della pratica non trovato.', 422);
    }
}
