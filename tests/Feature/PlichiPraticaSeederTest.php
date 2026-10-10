<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DocumentType;
use App\Models\PraticaStati;
use App\Models\PROFORMA\Pratica;
use App\Models\Task;
use Database\Seeders\PlichiPraticaSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlichiPraticaSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = ['mysql', 'mysql_proforma'];

    private function seed_plichi(): void
    {
        foreach (['Inserita', 'ACCETTATO PREVENTIVO', 'Richiesta Istruttoria', 'Richiesta Polizza', 'DELIBERATA', 'NOTIFICA', 'RIENTRO BENESTARE', 'PERFEZIONATA', 'LIQUIDATA', 'In attesa documenti originali'] as $state) {
            PraticaStati::query()->firstOrCreate(['stato_pratica' => $state], ['isrejected' => 0, 'isworking' => 1, 'isestingued' => 0]);
        }

        DocumentType::query()->create(['name' => 'Carta di Identità', 'slug' => 'carta-identita', 'nature' => 'incoming']);
        $this->seed(PlichiPraticaSeeder::class);
    }

    public function test_every_product_and_state_gets_its_plico_with_the_expected_documents(): void
    {
        $this->seed_plichi();

        $plico = Task::query()->where('name', 'Mutuo · DELIBERATA')->sole();
        $this->assertSame(['tipo_prodotto', 'Mutuo', 'stato_pratica', 'DELIBERATA', 'pratica'], [$plico->trigger_field, $plico->trigger_value, $plico->trigger_subfield, $plico->trigger_subvalue, $plico->taskable]);
        $this->assertEqualsCanonicalizing(['Delibera banca', 'Informazioni europee di base sul credito (SECCI)'], $plico->documentTypes->pluck('name')->all());
        $this->assertTrue($plico->documentTypes->every(fn ($type) => $type->pivot->is_required));

        $cessione = Task::query()->where('name', 'Cessione · NOTIFICA')->sole();
        $this->assertSame(['Notifica al datore di lavoro'], $cessione->documentTypes->pluck('name')->all());

        $this->assertTrue(DocumentType::query()->where('name', 'Contratto di finanziamento')->value('is_signed'));
    }

    public function test_pratica_matches_exactly_the_plico_of_its_product_and_state_regardless_of_case(): void
    {
        $this->seed_plichi();

        $names = Task::getAvailableFor(new Pratica(['tipo_prodotto' => 'MUTUO', 'stato_pratica' => 'deliberata']))->pluck('name')->all();

        $this->assertSame(['Mutuo · DELIBERATA'], $names);
        $this->assertSame([], Task::getAvailableFor(new Pratica(['tipo_prodotto' => 'Mutuo', 'stato_pratica' => 'LIQUIDATA']))->where('name', '!=', 'Mutuo · LIQUIDATA')->pluck('name')->all());
        $this->assertSame([], Task::getAvailableFor(new Pratica(['tipo_prodotto' => 'Utenza', 'stato_pratica' => 'DELIBERATA']))->pluck('name')->all());
    }

    public function test_client_plichi_depend_on_status_and_person_type(): void
    {
        $this->seed_plichi();

        $person = Task::getAvailableFor(new Client(['status' => 'raccolta_dati', 'is_person' => true]))->pluck('name')->all();
        $company = Task::getAvailableFor(new Client(['status' => 'raccolta_dati', 'is_person' => false]))->pluck('name')->all();

        $this->assertSame(['Cliente persona fisica · raccolta dati'], $person);
        $this->assertSame(['Cliente persona giuridica · raccolta dati'], $company);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed_plichi();
        $tasks = Task::query()->count();
        $links = DB::table('task_document_types')->count();

        $this->seed(PlichiPraticaSeeder::class);

        $this->assertSame($tasks, Task::query()->count());
        $this->assertSame($links, DB::table('task_document_types')->count());
    }
}
