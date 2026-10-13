<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DocumentType;
use App\Models\Pratica;
use App\Models\Task;
use Database\Seeders\PlichiCessioneGruppoSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;
use Unico\Core\Models\TipoProdottoSub;

class CessioneEmploymentDocumentGroupTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = ['mysql'];

    /**
     * @var array<string, string>
     */
    private const GROUPS_BY_CODE = [
        'EMP_PENS_INPS' => 'pensionati',
        'EMP_PENS_INPDAP' => 'pensionati',
        'EMP_PENS_ALTRI' => 'pensionati',
        'EMP_PRIVATO_SPA' => 'privati',
        'EMP_PRIV_ALTRO' => 'privati',
        'EMP_PRIV_SMALL' => 'privati',
        'EMP_STATALE' => 'pubblici',
        'EMP_PUBBLICO' => 'pubblici',
        'EMP_POSTALE' => 'pubblici',
        'EMP_FERROVIERE' => 'pubblici',
        'EMP_MEDICO' => 'pubblici',
        'EMP_PARAPUBBLICO' => 'parapubblici',
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const ISTRUTTORIA = [
        'pensionati' => [
            'Carta di Identità',
            'Codice fiscale / Tessera sanitaria',
            'Patente di Guida',
            'Cedolino Pensione',
            'Certificazione Unica (CU) ultimo anno',
            'Comunicazione Quota Cedibile INPS',
            'Estratto Conto INPS',
            'Modello OBISM',
            'Polizza Rischio Vita',
            'patronato',
            'Richiesta Conteggio Estintivo banca',
        ],
        'privati' => [
            'Carta di Identità',
            'Codice fiscale / Tessera sanitaria',
            'Patente di Guida',
            'Busta paga ultimo anno',
            'Busta paga anno precedente',
            'Certificazione Unica (CU) ultimo anno',
            'Contratto di lavoro',
            'Modello 730 / Redditi ultimo anno',
            'Modello 730 / Redditi anno precedente',
            'Estratto conto bancario',
            'Polizza Rischio Vita',
            'Polizza Rischio Impiego / Perdit Lavoro',
            'Richiesta Conteggio Estintivo banca',
        ],
        'pubblici' => [
            'Carta di Identità',
            'Codice fiscale / Tessera sanitaria',
            'Patente di Guida',
            'Busta paga ultimo anno',
            'Busta paga anno precedente',
            'Certificazione Unica (CU) ultimo anno',
            'Certificato di stipendio e quota cedibile',
            'Delega richiesta allegati statali',
            'Polizza Rischio Vita',
            'patronato',
            'Richiesta Conteggio Estintivo banca',
        ],
        'parapubblici' => [
            'Carta di Identità',
            'Codice fiscale / Tessera sanitaria',
            'Patente di Guida',
            'Busta paga ultimo anno',
            'Busta paga anno precedente',
            'Certificazione Unica (CU) ultimo anno',
            'Certificato di stipendio e quota cedibile',
            'Contratto di lavoro',
            'Polizza Rischio Vita',
            'Polizza Rischio Impiego / Perdit Lavoro',
            'Richiesta Conteggio Estintivo banca',
        ],
    ];

    /**
     * @var array<int, string>
     */
    private const INVIO_BANCA = [
        'Fascicolo completo retail CQ',
        'Delibera banca',
        'Informazioni europee di base sul credito (SECCI)',
    ];

    public function test_employment_document_group_is_assigned_to_the_twelve_cession_subtypes(): void
    {
        $column = collect(Schema::getColumns('tipoprodotto_sub'))
            ->firstWhere('name', 'employment_document_group');

        $this->assertIsArray($column);
        $this->assertStringContainsString('pensionati', (string) ($column['comment'] ?? ''));

        $actual = TipoProdottoSub::query()
            ->whereIn('code', array_keys(self::GROUPS_BY_CODE))
            ->get()
            ->mapWithKeys(fn (TipoProdottoSub $sub): array => [
                $sub->code => $sub->employment_document_group?->value,
            ])
            ->all();
        ksort($actual);

        $expected = self::GROUPS_BY_CODE;
        ksort($expected);

        $this->assertSame($expected, $actual);

        $migration = require dirname(__DIR__, 3).'/unico-core/database/migrations/2026_10_13_143000_add_employment_document_group_to_tipoprodotto_sub.php';
        $migration->up();

        $this->assertSame(12, TipoProdottoSub::query()->whereIn('code', array_keys(self::GROUPS_BY_CODE))->count());
        $this->assertSame('pubblici', TipoProdottoSub::query()->where('code', 'EMP_MEDICO')->value('employment_document_group')?->value);
    }

    public function test_missing_checklist_document_is_created_with_a_normalized_name(): void
    {
        $historical = DocumentType::query()->create([
            'name' => 'Convenzione COMPASS 2025',
            'slug' => 'convenzione-compass-2025',
            'nature' => 'incoming',
        ]);

        $this->seed(PlichiCessioneGruppoSeeder::class);

        $this->assertModelExists(DocumentType::query()->where('name', 'Busta paga ultimo anno')->where('phase', 'istruttoria')->sole());
        $this->assertModelExists(DocumentType::query()->where('name', 'Richiesta Conteggio Estintivo banca')->sole());
        $this->assertSame(0, DocumentType::query()->where('name', 'Busta paga 2025')->count());
        $this->assertSame(0, DocumentType::query()->where('name', 'Richiesta Conteggio Estintivo COMPASS')->count());

        $historical->refresh();
        $this->assertSame('Convenzione COMPASS 2025', $historical->name);
    }

    public function test_existing_normalized_document_type_is_reused(): void
    {
        $payslip = DocumentType::query()->create([
            'name' => 'Busta paga ultimo anno',
            'slug' => 'busta-paga-ultimo-anno',
            'nature' => 'incoming',
            'phase' => 'istruttoria',
        ]);
        $rawPayslip = DocumentType::query()->create([
            'name' => 'Busta paga 2025',
            'slug' => 'busta-paga-2025',
            'nature' => 'incoming',
        ]);
        $fascicolo = DocumentType::query()->create([
            'name' => 'Races VERS. 03_2026 - Fascicolo completo retail CQ compilabile',
            'slug' => 'fascicolo-completo-retail-cq',
            'nature' => 'incoming',
        ]);

        $this->seed(PlichiCessioneGruppoSeeder::class);

        $this->assertSame(1, DocumentType::query()->where('name', 'Busta paga ultimo anno')->count());
        $this->assertTrue($payslip->is(DocumentType::query()->where('name', 'Busta paga ultimo anno')->sole()));
        $rawPayslip->refresh();
        $this->assertSame('Busta paga 2025', $rawPayslip->name);

        $fascicolo->refresh();
        $this->assertSame('Races VERS. 03_2026 - Fascicolo completo retail CQ compilabile', $fascicolo->name);
        $this->assertSame('invio_banca', $fascicolo->phase);
        $this->assertSame(1, DocumentType::query()->where('name', 'like', '%Fascicolo completo retail CQ%')->count());
        $this->assertTrue(
            Task::query()->where('name', 'Cessione · privati')->firstOrFail()->documentTypes->contains($fascicolo),
        );
    }

    public function test_each_employment_group_has_an_istruttoria_plico_and_the_last_three_documents_go_to_invio_banca(): void
    {
        $this->seed(PlichiCessioneGruppoSeeder::class);
        $tasks = Task::query()->count();
        $links = Task::query()->withCount('documentTypes')->get()->sum('document_types_count');

        $this->seed(PlichiCessioneGruppoSeeder::class);

        $this->assertSame($tasks, Task::query()->count());
        $this->assertSame($links, Task::query()->withCount('documentTypes')->get()->sum('document_types_count'));

        foreach (self::ISTRUTTORIA as $group => $istruttoria) {
            $task = Task::query()->where('name', "Cessione · {$group}")->sole();

            $this->assertSame(
                ['pratica', 'tipo_prodotto', 'equals', 'Cessione', 'employment_document_group', $group],
                [$task->taskable, $task->trigger_field, $task->trigger_state, $task->trigger_value, $task->trigger_subfield, $task->trigger_subvalue],
            );

            $names = $task->documentTypes()->orderBy('document_requirements.sort_order')->pluck('name')->all();

            $this->assertSame([...$istruttoria, ...self::INVIO_BANCA], $names);

            $phases = $task->documentTypes->mapWithKeys(fn (DocumentType $type): array => [$type->name => $type->phase])->all();

            foreach ($istruttoria as $name) {
                $this->assertSame('istruttoria', $phases[$name]);
            }

            foreach (self::INVIO_BANCA as $name) {
                $this->assertSame('invio_banca', $phases[$name]);
            }
        }

        $pensionato = $this->client('Pensionato INPS');
        $statale = $this->client('Statale');
        $privato = $this->client('Privato SPA');
        $parapubblico = $this->client('Parapubblico');

        $this->assertSame(['Cessione · pensionati'], $this->groupPlichi($pensionato, 'Cessione'));
        $this->assertSame(['Cessione · pubblici'], $this->groupPlichi($statale, 'Cessione'));
        $this->assertSame(['Cessione · privati'], $this->groupPlichi($privato, 'Cessione'));
        $this->assertSame(['Cessione · parapubblici'], $this->groupPlichi($parapubblico, 'Cessione'));
        $this->assertSame([], $this->groupPlichi($pensionato, 'Mutuo'));
        $this->assertSame([], $this->groupPlichi(null, 'Cessione'));
    }

    private function client(string $typology): Client
    {
        return Client::query()->create([
            'name' => 'Rossi',
            'first_name' => 'Mario',
            'is_person' => true,
            'tax_code' => strtoupper(Str::random(16)),
            'employment_typology' => $typology,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function groupPlichi(?Client $client, string $product): array
    {
        $pratica = new Pratica([
            'tipo_prodotto' => $product,
            'codice_fiscale' => $client?->tax_code,
        ]);

        return Task::getAvailableFor($pratica)
            ->pluck('name')
            ->filter(fn (string $name): bool => str_starts_with($name, 'Cessione · '))
            ->values()
            ->all();
    }
}
