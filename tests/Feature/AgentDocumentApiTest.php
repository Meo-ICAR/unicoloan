<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use App\Models\Company;
use App\Models\DocumentType;
use App\Models\PdfModule;
use App\Models\PROFORMA\Fornitore;
use App\Models\Tipoprodotto;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Funzioni documentali di unicoloan per unicoagent: moduli, template, modulo compilato, file e firma OTP. */
class AgentDocumentApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    private array $issued;

    private PdfModule $module;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('unico-core.documents_disk'));
        Storage::fake('public');
        Storage::fake(config('media-library.disk_name'));
        config(['signature.driver' => 'fake']);
        Company::factory()->create();
        Tipoprodotto::unguarded(fn () => Tipoprodotto::firstOrCreate(['tipo_prodotto' => 'Altro'], ['name' => 'Altro']));
        $this->issued = ApiClient::issue('unicoagent');
        Fornitore::create(['id' => (string) Str::uuid(), 'name' => 'ZZ AGENTE UNO', 'piva' => '99900000001']);

        $type = DocumentType::create(['name' => 'Modulo QAV ZZ', 'slug' => 'qav-zz', 'nature' => 'template_fillable', 'is_monitored' => true, 'duration' => 12, 'duration_unit' => 'months']);
        $this->module = PdfModule::factory()->create([
            'name' => 'QAV ZZ', 'file_path' => 'module/qav-zz.pdf', 'document_type_id' => $type->id,
            'signature_slots' => [
                ['slot' => 'cliente', 'role' => 'client', 'page' => 1, 'x' => 50.0, 'y' => 700.0, 'width' => 150.0, 'height' => 40.0],
                ['slot' => 'collaboratore', 'role' => 'collaborator', 'page' => 1, 'x' => 300.0, 'y' => 700.0, 'width' => 150.0, 'height' => 40.0],
            ],
        ]);
        Storage::disk('public')->put('module/qav-zz.pdf', file_get_contents(base_path('tests/Fixtures/pdf/modulo-prova.pdf')));
    }

    private function request(string $reference = 'DOC-0001'): array
    {
        return [
            'riferimento' => $reference, 'prodotto' => 'quinto', 'pratica' => ['importo_richiesto' => 20000],
            'cliente' => ['cognome' => 'Rossi', 'nome' => 'Mario', 'codice_fiscale' => 'RSSMRA80A01H501U', 'email' => 'mario@example.com', 'telefono' => '+393331234567'],
            'agente' => ['partita_iva' => '99900000001'],
        ];
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


    private function base(string $reference = 'DOC-0001'): string
    {
        $this->signedJson('POST', '/api/agente/v1/richieste', $this->request($reference))->assertSuccessful();

        return "/api/agente/v1/richieste/{$reference}";
    }

    public function test_elenca_i_moduli_pertinenti_con_i_riquadri_di_firma(): void
    {
        $base = $this->base();

        $moduli = $this->signedJson('GET', "{$base}/moduli")->assertOk()->json('moduli');

        $this->assertSame($this->module->id, $moduli[0]['id']);
        $this->assertSame('qav-zz', $moduli[0]['tipo_documento']);
        $this->assertSame(['cliente', 'collaboratore'], $moduli[0]['firma']);
        $this->assertIsArray($moduli[0]['dati_mancanti']);
    }

    public function test_un_modulo_non_pertinente_non_si_vede_ne_si_scarica(): void
    {
        $base = $this->base();
        $this->module->update(['tipi_prodotto' => ['Mutuo inesistente']]);

        $this->assertSame([], $this->signedJson('GET', "{$base}/moduli")->assertOk()->json('moduli'));
        $this->signedJson('GET', "{$base}/moduli/{$this->module->id}/template")->assertNotFound()->assertJsonPath('codice', 'modulo_non_trovato');
        $this->signedJson('POST', "{$base}/moduli/{$this->module->id}/compilato", [])->assertNotFound();
    }

    public function test_scarica_il_template_vuoto_con_il_suo_hash(): void
    {
        $base = $this->base();

        $response = $this->signedJson('GET', "{$base}/moduli/{$this->module->id}/template")->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertSame(hash('sha256', $response->getContent()), $response->headers->get('X-Content-Sha256'));
    }

    public function test_template_mancante_nello_storage_da_404(): void
    {
        $base = $this->base();
        Storage::disk('public')->delete('module/qav-zz.pdf');

        $this->signedJson('GET', "{$base}/moduli/{$this->module->id}/template")->assertNotFound()->assertJsonPath('codice', 'template_non_disponibile');
    }

    public function test_compila_il_modulo_e_lo_rende_scaricabile(): void
    {
        $base = $this->base();

        $document = $this->signedJson('POST', "{$base}/moduli/{$this->module->id}/compilato", [])->assertCreated()->json();
        $file = $this->signedJson('GET', "{$base}/documenti/{$document['id']}/file")->assertOk();

        $this->assertTrue($document['ricevuto']);
        $this->assertStringStartsWith('%PDF', $file->getContent());
    }

    public function test_un_documento_di_unaltra_richiesta_non_si_raggiunge(): void
    {
        $base = $this->base('DOC-0001');
        $other = $this->base('DOC-0002');
        $document = $this->signedJson('POST', "{$base}/moduli/{$this->module->id}/compilato", [])->json();

        $this->signedJson('GET', "{$other}/documenti/{$document['id']}/file")->assertNotFound()->assertJsonPath('codice', 'documento_non_trovato');
    }

    public function test_richiesta_inesistente_e_credenziali_sbagliate(): void
    {
        $this->signedJson('GET', '/api/agente/v1/richieste/NON-ESISTE/moduli')->assertNotFound();
        $this->getJson('/api/agente/v1/richieste/DOC-0001/moduli')->assertUnauthorized();
    }

    public function test_chiede_la_firma_otp_e_ne_legge_lo_stato(): void
    {
        $base = $this->base();
        $document = $this->signedJson('POST', "{$base}/moduli/{$this->module->id}/compilato", [])->json();

        $created = $this->signedJson('POST', "{$base}/documenti/{$document['id']}/firma", [
            'firmatari' => ['cliente' => ['telefono' => '+393331234567'], 'collaboratore' => ['telefono' => '+393339876543', 'email' => 'agente@example.com']],
        ])->assertCreated();

        $created->assertJsonPath('stato', 'sent')->assertJsonPath('firmata', false)->assertJsonCount(2, 'firmatari');
        $this->signedJson('GET', "{$base}/documenti/{$document['id']}/firma")->assertOk()->assertJsonPath('id', $created->json('id'));
    }

    public function test_una_seconda_firma_con_la_prima_aperta_e_rifiutata(): void
    {
        $base = $this->base();
        $document = $this->signedJson('POST', "{$base}/moduli/{$this->module->id}/compilato", [])->json();
        $contacts = ['firmatari' => ['cliente' => ['telefono' => '+393331234567'], 'collaboratore' => ['telefono' => '+393339876543', 'email' => 'agente@example.com']]];

        $this->signedJson('POST', "{$base}/documenti/{$document['id']}/firma", $contacts)->assertCreated();
        $this->signedJson('POST', "{$base}/documenti/{$document['id']}/firma", $contacts)->assertStatus(422)->assertJsonPath('codice', 'firma_non_richiesta');
    }

    public function test_senza_stato_di_firma_il_documento_da_404(): void
    {
        $base = $this->base();
        $document = $this->signedJson('POST', "{$base}/moduli/{$this->module->id}/compilato", [])->json();

        $this->signedJson('GET', "{$base}/documenti/{$document['id']}/firma")->assertNotFound()->assertJsonPath('codice', 'firma_assente');
    }

    public function test_dati_di_contatto_non_validi_sono_respinti(): void
    {
        $base = $this->base();
        $document = $this->signedJson('POST', "{$base}/moduli/{$this->module->id}/compilato", [])->json();

        $this->signedJson('POST', "{$base}/documenti/{$document['id']}/firma", ['firmatari' => ['cliente' => ['email' => 'non-una-mail']]])->assertStatus(422);
    }

    public function test_dopo_la_firma_lo_stesso_id_da_il_documento_firmato(): void
    {
        $base = $this->base();
        $document = $this->signedJson('POST', "{$base}/moduli/{$this->module->id}/compilato", [])->json();
        $signature = $this->signedJson('POST', "{$base}/documenti/{$document['id']}/firma", [
            'firmatari' => ['cliente' => ['telefono' => '+393331234567'], 'collaboratore' => ['telefono' => '+393339876543', 'email' => 'agente@example.com']],
        ])->assertCreated()->json();

        $request = \App\Models\SignatureRequest::findOrFail($signature['id']);
        app(\App\Services\Signature\SignatureProviderManager::class)->provider('fake')->completeAll($request->provider_ref);
        app(\App\Services\Signature\SignatureRequestService::class)->reconcile($request);

        $this->signedJson('GET', "{$base}/documenti/{$document['id']}/firma")->assertOk()->assertJsonPath('firmata', true)->assertJsonPath('stato', 'signed');
        $this->assertStringEndsWith('%FAKE-SIGNED', rtrim($this->signedJson('GET', "{$base}/documenti/{$document['id']}/file")->assertOk()->getContent()));
    }
}
