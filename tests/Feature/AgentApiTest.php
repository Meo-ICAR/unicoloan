<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use App\Models\Client;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\PROFORMA\Fornitore;
use App\Models\PROFORMA\Pratica;
use App\Models\Task;
use App\Models\Tipoprodotto;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use Unico\Core\Enums\DocumentStatus;

/** API in ingresso per le app che consegnano richieste di finanziamento (unicoagent). */
class AgentApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @var array<int, string> */
    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    /** @var array{client: ApiClient, token: string, secret: string} */
    private array $issued;

    private DocumentType $identity;

    private DocumentType $income;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('unico-core.documents_disk'));
        Storage::fake('public');
        Company::factory()->create();
        Tipoprodotto::unguarded(function () {
            Tipoprodotto::firstOrCreate(['tipo_prodotto' => 'Altro'], ['name' => 'Altro']);
            Tipoprodotto::firstOrCreate(['tipo_prodotto' => 'Cessione del quinto'], ['name' => 'Cessione del quinto']);
        });
        $this->issued = ApiClient::issue('unicoagent');
        Fornitore::create(['id' => (string) Str::uuid(), 'name' => 'ZZ AGENTE UNO', 'piva' => '99900000001']);

        $this->identity = DocumentType::create(['name' => 'Carta identità', 'slug' => 'carta-identita-zz', 'nature' => 'incoming', 'is_client' => true]);
        $this->income = DocumentType::create(['name' => 'Busta paga', 'slug' => 'busta-paga-zz', 'nature' => 'incoming', 'is_practice' => true]);
        $plicoClient = Task::create(['name' => 'Plico cliente ZZ', 'is_active' => true, 'taskable' => 'client']);
        $plicoClient->documentTypes()->attach($this->identity->getKey(), ['is_required' => true]);
        $plicoPratica = Task::create(['name' => 'Plico pratica ZZ', 'is_active' => true, 'taskable' => 'pratica']);
        $plicoPratica->documentTypes()->attach($this->income->getKey(), ['is_required' => true]);
    }

    /** Il database Proforma delle prove non è vuoto: si contano solo le pratiche create da questi test. */
    private function viaApi(): \Illuminate\Database\Eloquent\Builder
    {
        return Pratica::query()->where('codice_pratica', 'like', config('agent_api.pratica_prefix').'%');
    }

    /** PDF con l'intestazione vera (i controlli automatici scartano i PDF che non cominciano con %PDF). */
    private function pdf(string $name = 'a.pdf', string $marker = 'a'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n% {$marker}\n".str_repeat('x', 200));
    }

    private function request(string $reference = 'FIN-2026-0001', array $override = []): array
    {
        return array_replace_recursive([
            'riferimento' => $reference,
            'prodotto' => 'quinto',
            'prodotto_etichetta' => 'Cessione del quinto',
            'pratica' => ['importo_richiesto' => 20000],
            'cliente' => ['cognome' => 'Rossi', 'nome' => 'Mario', 'codice_fiscale' => 'RSSMRA80A01H501U', 'email' => 'mario@example.com', 'telefono' => '+393331234567'],
            'agente' => ['partita_iva' => '99900000001'],
        ], $override);
    }

    private function signature(string $method, string $path, string $contentHash, ?int $timestamp = null, ?string $secret = null): array
    {
        $timestamp ??= time();
        $signature = hash_hmac('sha256', implode('.', [$timestamp, $method, $path, $contentHash]), $secret ?? $this->issued['secret']);

        return ['X-Timestamp' => (string) $timestamp, 'X-Signature' => $signature];
    }

    /** @param  array<string,string>  $headers */
    private function signedJson(string $method, string $path, ?array $json = null, array $headers = []): \Illuminate\Testing\TestResponse
    {
        $body = $json === null ? '' : json_encode($json);
        $headers += ['Authorization' => 'Bearer '.$this->issued['token']] + $this->signature($method, $path, hash('sha256', $body));

        return $this->call($method, $path, [], [], [], $this->server($headers), $body);
    }

    private function server(array $headers): array
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $server;
    }

    private function upload(string $reference, string $type, UploadedFile $file, array $extra = [], array $headers = []): \Illuminate\Testing\TestResponse
    {
        $path = "/api/agente/v1/richieste/{$reference}/documenti";
        $hash = hash_file('sha256', $file->getRealPath());
        $headers += ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->issued['token'], 'X-Content-Sha256' => $hash] + $this->signature('POST', $path, $hash);

        return $this->post($path, ['tipo' => $type, 'file' => $file] + $extra, $headers);
    }

    // --- autenticazione -----------------------------------------------------------------------------------------

    public function test_senza_credenziali_valide_si_riceve_401(): void
    {
        $this->getJson('/api/agente/v1/tipi-documento')->assertUnauthorized();
        $this->getJson('/api/agente/v1/tipi-documento', ['Authorization' => 'Bearer sbagliato'])->assertUnauthorized();
    }

    public function test_serve_la_firma_e_deve_essere_corretta_e_recente(): void
    {
        $path = '/api/agente/v1/tipi-documento';
        $auth = ['Authorization' => 'Bearer '.$this->issued['token']];

        $this->getJson($path, $auth)->assertUnauthorized();
        $this->getJson($path, $auth + $this->signature('GET', $path, hash('sha256', ''), null, 'segreto-sbagliato'))->assertUnauthorized();
        $this->getJson($path, $auth + $this->signature('GET', $path, hash('sha256', ''), time() - 3600))->assertUnauthorized();
        $this->getJson($path, $auth + $this->signature('GET', '/api/agente/v1/altro', hash('sha256', '')))->assertUnauthorized();
        $this->signedJson('GET', $path)->assertOk();
    }

    public function test_un_client_disattivato_non_entra(): void
    {
        $this->issued['client']->update(['is_active' => false]);

        $this->signedJson('GET', '/api/agente/v1/tipi-documento')->assertUnauthorized();
    }

    public function test_il_corpo_non_si_puo_alterare_dopo_la_firma(): void
    {
        $path = '/api/agente/v1/richieste';
        $headers = ['Authorization' => 'Bearer '.$this->issued['token']] + $this->signature('POST', $path, hash('sha256', json_encode($this->request())));

        $this->call('POST', $path, [], [], [], $this->server($headers), json_encode($this->request('FIN-ALTRO')))->assertUnauthorized();
        $this->assertSame(0, $this->viaApi()->count());
    }

    public function test_la_verifica_della_firma_si_puo_disattivare_ma_il_token_resta_obbligatorio(): void
    {
        config(['agent_api.require_signature' => false]);

        $this->getJson('/api/agente/v1/tipi-documento', ['Authorization' => 'Bearer '.$this->issued['token']])->assertOk();
        $this->getJson('/api/agente/v1/tipi-documento')->assertUnauthorized();
    }

    // --- cliente e pratica --------------------------------------------------------------------------------------

    public function test_una_richiesta_crea_cliente_pratica_e_documenti_richiesti(): void
    {
        $response = $this->signedJson('POST', '/api/agente/v1/richieste', $this->request())->assertCreated();

        $response->assertJsonPath('riferimento', 'FIN-2026-0001')
            ->assertJsonPath('codice_pratica', 'WA-FIN-2026-0001')
            ->assertJsonPath('stato_pratica', 'INSERITA')
            ->assertJsonPath('creata', true);

        $pratica = Pratica::where('codice_pratica', 'WA-FIN-2026-0001')->sole();
        $this->assertSame('Cessione del quinto', $pratica->tipo_prodotto);
        $this->assertSame('99900000001', $pratica->partita_iva_agente);
        $this->assertSame('RSSMRA80A01H501U', $pratica->codice_fiscale);

        $client = Client::where('tax_code', 'RSSMRA80A01H501U')->sole();
        $this->assertSame('mario@example.com', $client->email);
        $response->assertJsonPath('cliente_id', $client->getKey());

        $tipi = collect($response->json('documenti'))->pluck('stato', 'tipo');
        $this->assertEqualsCanonicalizing(['carta-identita-zz' => 'richiesto', 'busta-paga-zz' => 'richiesto'], $tipi->all());
        $this->assertCount(2, $tipi);
    }

    public function test_ripetere_la_richiesta_non_duplica_nulla(): void
    {
        $first = $this->signedJson('POST', '/api/agente/v1/richieste', $this->request())->assertCreated();
        $documents = Document::count();

        $second = $this->signedJson('POST', '/api/agente/v1/richieste', $this->request())->assertOk()->assertJsonPath('creata', false);

        $this->assertSame($first->json('pratica_id'), $second->json('pratica_id'));
        $this->assertSame(1, $this->viaApi()->count());
        $this->assertSame(1, Client::where('tax_code', 'RSSMRA80A01H501U')->count());
        $this->assertSame($documents, Document::count());
    }

    public function test_un_agente_sconosciuto_e_rifiutato(): void
    {
        $this->signedJson('POST', '/api/agente/v1/richieste', $this->request('FIN-2', ['agente' => ['partita_iva' => '00000000000']]))
            ->assertStatus(422)->assertJsonPath('codice', 'agente_sconosciuto');

        $this->assertSame(0, $this->viaApi()->count());
    }

    public function test_un_cliente_gia_di_un_altro_agente_non_si_prende(): void
    {
        Pratica::create(['id' => (string) Str::uuid(), 'codice_pratica' => 'ZZ-1', 'codice_fiscale' => 'RSSMRA80A01H501U', 'partita_iva_agente' => '11111111111', 'stato_pratica' => 'INSERITA', 'data_inserimento_pratica' => today()]);
        Client::create(['is_person' => true, 'is_client' => true, 'name' => 'Rossi', 'first_name' => 'Mario', 'tax_code' => 'RSSMRA80A01H501U']);

        $this->signedJson('POST', '/api/agente/v1/richieste', $this->request('FIN-3'))
            ->assertStatus(409)->assertJsonPath('codice', 'cliente_di_altro_agente');
    }

    public function test_i_dati_obbligatori_sono_controllati(): void
    {
        $this->signedJson('POST', '/api/agente/v1/richieste', $this->request('FIN-4', ['cliente' => ['codice_fiscale' => '']]))->assertStatus(422)->assertJsonValidationErrors(['cliente.codice_fiscale']);
        $this->signedJson('POST', '/api/agente/v1/richieste', ['riferimento' => 'con spazi!'] + $this->request())->assertStatus(422)->assertJsonValidationErrors(['riferimento']);
        $this->assertSame(0, $this->viaApi()->count());
    }

    public function test_lo_stato_della_richiesta_si_legge(): void
    {
        $this->signedJson('GET', '/api/agente/v1/richieste/FIN-INESISTENTE')->assertNotFound();

        $this->signedJson('POST', '/api/agente/v1/richieste', $this->request())->assertCreated();

        $this->signedJson('GET', '/api/agente/v1/richieste/FIN-2026-0001')->assertOk()
            ->assertJsonPath('stato_pratica', 'INSERITA')->assertJsonCount(2, 'documenti');
    }

    // --- documenti ----------------------------------------------------------------------------------------------

    public function test_un_file_va_sul_documento_richiesto_con_canale_e_riferimento(): void
    {
        $this->signedJson('POST', '/api/agente/v1/richieste', $this->request())->assertCreated();

        $response = $this->upload('FIN-2026-0001', 'carta-identita-zz', $this->pdf('cid.pdf', 'cid'), ['id_origine' => 'att-42', 'analisi' => json_encode(['leggibile' => true])])
            ->assertCreated()->assertJsonPath('duplicato', false)->assertJsonPath('ricevuto', true);

        $document = Document::findOrFail($response->json('id'));
        $this->assertSame($this->identity->getKey(), $document->document_type_id);
        $this->assertSame('whatsapp', $document->channel);
        $this->assertSame('att-42', $document->channel_ref);
        $this->assertSame(['app' => 'unicoagent', 'analisi' => ['leggibile' => true]], $document->metadata['origine']);
        $this->assertTrue($document->hasMedia(Document::COLLECTION));
        $this->assertSame(64, strlen((string) $document->file_hash));
        // i controlli automatici partono da soli e il documento esce da «richiesto»
        $this->assertSame(DocumentStatus::UPLOADED, $document->status);
        $this->assertSame(2, Document::count());
    }

    public function test_lo_stesso_file_due_volte_non_si_carica_di_nuovo(): void
    {
        $this->signedJson('POST', '/api/agente/v1/richieste', $this->request())->assertCreated();
        $first = $this->upload('FIN-2026-0001', 'carta-identita-zz', $this->pdf('cid.pdf', 'uguale'))->assertCreated();
        $again = $this->upload('FIN-2026-0001', 'carta-identita-zz', $this->pdf('copia.pdf', 'uguale'))->assertOk()->assertJsonPath('duplicato', true);

        $this->assertSame($first->json('id'), $again->json('id'));
        $this->assertCount(1, Document::findOrFail($first->json('id'))->getMedia(Document::COLLECTION));
    }

    public function test_un_file_diverso_dall_hash_dichiarato_e_rifiutato(): void
    {
        $this->signedJson('POST', '/api/agente/v1/richieste', $this->request())->assertCreated();
        $path = '/api/agente/v1/richieste/FIN-2026-0001/documenti';
        $declared = hash('sha256', 'altro contenuto');
        $headers = ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->issued['token'], 'X-Content-Sha256' => $declared] + $this->signature('POST', $path, $declared);

        $this->post($path, ['tipo' => 'carta-identita-zz', 'file' => UploadedFile::fake()->createWithContent('a.pdf', '%PDF contenuto vero')], $headers)
            ->assertStatus(422)->assertJsonPath('codice', 'hash_non_corrispondente');

        $this->assertFalse(Document::where('document_type_id', $this->identity->getKey())->first()->hasMedia(Document::COLLECTION));
    }

    public function test_l_hash_e_obbligatorio_e_i_tipi_di_file_sono_limitati(): void
    {
        $this->signedJson('POST', '/api/agente/v1/richieste', $this->request())->assertCreated();
        $path = '/api/agente/v1/richieste/FIN-2026-0001/documenti';

        $this->post($path, ['tipo' => 'carta-identita-zz', 'file' => $this->pdf()],
            ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->issued['token']] + $this->signature('POST', $path, hash('sha256', '')))
            ->assertStatus(422)->assertJsonPath('codice', 'hash_mancante');

        $this->upload('FIN-2026-0001', 'carta-identita-zz', UploadedFile::fake()->createWithContent('script.php', '<?php echo 1;'))->assertStatus(422)->assertJsonValidationErrors(['file']);
    }

    public function test_un_tipo_sconosciuto_o_una_richiesta_inesistente_sono_rifiutati(): void
    {
        $this->signedJson('POST', '/api/agente/v1/richieste', $this->request())->assertCreated();
        $file = $this->pdf();

        $this->upload('FIN-2026-0001', 'tipo-che-non-esiste', $file)->assertStatus(422)->assertJsonPath('codice', 'tipo_documento_sconosciuto');
        $this->upload('FIN-INESISTENTE', 'carta-identita-zz', $file)->assertNotFound();
    }

    public function test_i_codici_dell_agente_si_traducono_con_la_mappa(): void
    {
        config(['agent_api.document_map' => ['documento_identita' => 'carta-identita-zz']]);
        $this->signedJson('POST', '/api/agente/v1/richieste', $this->request())->assertCreated();

        $this->upload('FIN-2026-0001', 'documento_identita', $this->pdf())->assertCreated()->assertJsonPath('tipo', 'carta-identita-zz');
    }

    public function test_un_documento_non_previsto_dal_plico_si_aggiunge_alla_pratica(): void
    {
        $extra = DocumentType::create(['name' => 'Estratto conto', 'slug' => 'estratto-conto-zz', 'nature' => 'incoming']);
        $this->signedJson('POST', '/api/agente/v1/richieste', $this->request())->assertCreated();

        $this->upload('FIN-2026-0001', 'estratto-conto-zz', $this->pdf())->assertCreated();

        $this->assertSame(3, Document::count());
        $this->assertTrue(Document::where('document_type_id', $extra->getKey())->sole()->hasMedia(Document::COLLECTION));
    }

    public function test_un_documento_respinto_riporta_il_motivo_nello_stato(): void
    {
        $created = $this->signedJson('POST', '/api/agente/v1/richieste', $this->request())->assertCreated();
        $id = collect($created->json('documenti'))->firstWhere('tipo', 'carta-identita-zz')['id'];
        Document::whereKey($id)->first()->update(['status' => DocumentStatus::REJECTED->value, 'rejection_note' => 'Illeggibile']);

        $document = collect($this->signedJson('GET', '/api/agente/v1/richieste/FIN-2026-0001')->json('documenti'))->firstWhere('id', $id);

        $this->assertSame('respinto', $document['stato']);
        $this->assertSame('Illeggibile', $document['motivo_rifiuto']);
    }

    public function test_l_elenco_dei_tipi_documento_mostra_gli_alias(): void
    {
        config(['agent_api.document_map' => ['documento_identita' => 'carta-identita-zz']]);

        $tipi = collect($this->signedJson('GET', '/api/agente/v1/tipi-documento')->assertOk()->json('tipi'));

        $this->assertEqualsCanonicalizing(['carta-identita-zz', 'busta-paga-zz'], $tipi->pluck('tipo')->all());
        $this->assertSame(['documento_identita'], $tipi->firstWhere('tipo', 'carta-identita-zz')['alias']);
    }

    // --- credenziali --------------------------------------------------------------------------------------------

    public function test_il_comando_crea_le_credenziali_una_volta_sola(): void
    {
        $this->artisan('agent-api:client', ['name' => 'nuova-app'])->assertSuccessful()->expectsOutputToContain('Token')->expectsOutputToContain('Segreto');
        $this->artisan('agent-api:client', ['name' => 'nuova-app'])->assertFailed();

        $old = ApiClient::where('name', 'nuova-app')->value('token_hash');
        $this->artisan('agent-api:client', ['name' => 'nuova-app', '--rotate' => true])->assertSuccessful();
        $this->assertNotSame($old, ApiClient::where('name', 'nuova-app')->value('token_hash'));
    }

    public function test_il_segreto_e_cifrato_e_il_token_non_si_conserva_in_chiaro(): void
    {
        $row = \DB::table('api_clients')->where('name', 'unicoagent')->first();

        $this->assertStringNotContainsString($this->issued['secret'], $row->secret);
        $this->assertNotSame($this->issued['token'], $row->token_hash);
        $this->assertSame(hash('sha256', $this->issued['token']), $row->token_hash);
    }
}
