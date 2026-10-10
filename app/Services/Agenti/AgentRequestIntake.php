<?php

namespace App\Services\Agenti;

use App\Models\Client;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\PROFORMA\Fornitore;
use App\Models\PROFORMA\Pratica;
use App\Models\Task;
use App\Models\Tipoprodotto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Unico\Core\Enums\DocumentStatus;

/**
 * Accoglie una richiesta di finanziamento consegnata da un'app esterna (unicoagent): trova o crea il cliente, crea la pratica
 * come fa il portale agenti, genera i documenti richiesti dai plichi e riceve i file. Il riferimento della richiesta rende
 * l'invio idempotente: ripeterlo non duplica nulla.
 */
class AgentRequestIntake
{
    public function __construct(private ClientForPraticaCreator $clients) {}

    public static function codiceFor(string $riferimento): string
    {
        return config('agent_api.pratica_prefix').$riferimento;
    }

    public function find(string $riferimento): ?Pratica
    {
        return Pratica::query()->where('codice_pratica', self::codiceFor($riferimento))->first();
    }

    /**
     * @param  array<string,mixed>  $data  richiesta già validata
     * @return array{pratica: Pratica, client: Client, created: bool}
     *
     * @throws AgentIntakeException
     */
    public function intake(array $data): array
    {
        $existing = $this->find($data['riferimento']);

        if ($existing !== null) {
            return ['pratica' => $existing, 'client' => $this->clientOf($existing), 'created' => false];
        }

        $agent = Fornitore::query()->where('piva', trim((string) $data['agente']['partita_iva']))->first()
            ?? throw new AgentIntakeException('agente_sconosciuto', 'Nessun agente con questa partita IVA.');

        $cliente = $data['cliente'];
        $taxCode = Str::upper(trim($cliente['codice_fiscale']));

        if (! $this->clients->isUsableByAgent($taxCode, $agent->piva)) {
            throw new AgentIntakeException('cliente_di_altro_agente', 'Il cliente risulta già gestito da un altro agente.', 409);
        }

        $client = $this->clients->findOrCreate($taxCode, false, (string) $cliente['cognome'], $cliente['nome'] ?? null, (string) ($cliente['email'] ?? ''), (string) ($cliente['telefono'] ?? ''));

        $pratica = Pratica::create([
            'id' => (string) Str::uuid(),
            'codice_pratica' => self::codiceFor($data['riferimento']),
            'nome_cliente' => $cliente['nome'] ?? null,
            'cognome_cliente' => $cliente['cognome'],
            'codice_fiscale' => $taxCode,
            'tipo_prodotto' => $this->tipoProdotto($data),
            'amount' => $data['pratica']['importo_richiesto'] ?? null,
            'stato_pratica' => 'INSERITA',
            'data_inserimento_pratica' => today(),
            'denominazione_agente' => $agent->name,
            'partita_iva_agente' => $agent->piva,
            'is_notowned' => false,
        ]);

        // Documenti richiesti dai plichi per il cliente e per la pratica (stato «richiesto»).
        Task::applyPlichiTo($client);
        Task::applyPlichiTo($pratica);

        return ['pratica' => $pratica, 'client' => $client, 'created' => true];
    }

    /**
     * Il tipo prodotto della pratica deve esistere in `tipoprodotto` (chiave esterna): si usa la mappa configurata, poi
     * l'etichetta del prodotto se coincide con un tipo, altrimenti il tipo di ripiego (`agent_api.default_tipo_prodotto`).
     */
    private function tipoProdotto(array $data): ?string
    {
        $candidates = array_filter([
            config('agent_api.product_map.'.($data['prodotto'] ?? '')),
            $data['prodotto_etichetta'] ?? null,
            $data['prodotto'] ?? null,
            config('agent_api.default_tipo_prodotto'),
        ]);

        foreach ($candidates as $candidate) {
            $found = Tipoprodotto::query()->whereRaw('lower(tipo_prodotto) = ?', [mb_strtolower((string) $candidate)])->value('tipo_prodotto');

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /** Tutti i documenti della richiesta: quelli del cliente e quelli della pratica. @return Collection<int, Document> */
    public function documents(Pratica $pratica): Collection
    {
        return $pratica->documents()->with('documentType')->get()
            ->merge($this->clientOf($pratica)->documents()->with('documentType')->get())
            ->values();
    }

    /**
     * Riceve un file: lo associa al documento richiesto di quel tipo (o ne crea uno) e lo carica. I controlli automatici
     * partono da soli al caricamento (VerifyUploadedDocument).
     *
     * @param  array<string,mixed>  $analysis  esito dell'analisi già fatta dall'app chiamante, conservato nei metadati
     * @return array{document: Document, duplicate: bool}
     *
     * @throws AgentIntakeException
     */
    public function receiveFile(Pratica $pratica, string $type, UploadedFile $file, string $sha256, ?string $originId, array $analysis = []): array
    {
        if (! hash_equals(strtolower($sha256), hash_file('sha256', $file->getRealPath()))) {
            throw new AgentIntakeException('hash_non_corrispondente', 'Il file ricevuto non corrisponde all\'hash dichiarato.');
        }

        $documentType = $this->resolveType($type);
        $documents = $this->documents($pratica)->where('document_type_id', $documentType->getKey());

        if ($documents->contains(fn (Document $d): bool => $d->file_hash === strtolower($sha256))) {
            return ['document' => $documents->first(fn (Document $d): bool => $d->file_hash === strtolower($sha256)), 'duplicate' => true];
        }

        $document = $documents->first(fn (Document $d): bool => ! $d->hasMedia(Document::COLLECTION))
            ?? $pratica->documents()->create([
                'document_type_id' => $documentType->getKey(),
                'name' => $documentType->name,
                'status' => DocumentStatus::PENDING->value,
                'company_id' => Company::query()->value('id'),
            ]);

        $document->forceFill([
            'channel' => 'whatsapp',
            'channel_ref' => $originId,
            'metadata' => array_merge((array) $document->metadata, ['origine' => array_filter(['app' => 'unicoagent', 'analisi' => $analysis ?: null])]),
        ])->save();

        $document->addMedia($file)->usingFileName(Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'.'.strtolower($file->getClientOriginalExtension() ?: $file->extension()))
            ->toMediaCollection(Document::COLLECTION);

        return ['document' => $document->fresh(), 'duplicate' => false];
    }

    /** @throws AgentIntakeException */
    public function resolveType(string $type): DocumentType
    {
        $wanted = (string) config('agent_api.document_map.'.$type, $type);

        return DocumentType::query()->where('slug', $wanted)->orWhere('code', $wanted)->first()
            ?? throw new AgentIntakeException('tipo_documento_sconosciuto', "Tipo documento «{$type}» non riconosciuto.");
    }

    public function clientOf(Pratica $pratica): Client
    {
        return Client::query()->where('tax_code', Str::upper(trim((string) $pratica->codice_fiscale)))->firstOrFail();
    }
}
