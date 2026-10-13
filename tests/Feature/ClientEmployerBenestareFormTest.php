<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Pages\EditClient;
use App\Models\Client;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ClientEmployerBenestareFormTest extends TestCase
{
    /**
     * @var array<int, string>
     */
    protected array $connectionsToTransact = ['mysql'];

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create());
    }

    public function test_benestare_fields_are_hidden_for_a_person(): void
    {
        $client = $this->makeClient();

        Livewire::test(EditClient::class, ['record' => $client->getKey()])
            ->assertFormFieldHidden('richiesta_benestare')
            ->assertFormFieldHidden('ritiro_benestare')
            ->assertFormFieldHidden('notifica');
    }

    public function test_company_saves_benestare_notes(): void
    {
        $this->assertBenestareColumns();

        $client = $this->makeClient([
            'name' => 'Acme Spa',
            'first_name' => null,
            'is_person' => false,
        ]);

        Livewire::test(EditClient::class, ['record' => $client->getKey()])
            ->assertFormFieldVisible('richiesta_benestare')
            ->assertFormFieldVisible('ritiro_benestare')
            ->assertFormFieldVisible('notifica')
            ->fillForm([
                'richiesta_benestare' => 'Richiedere il benestare all\'ufficio del personale.',
                'ritiro_benestare' => 'Ritirare l\'atto allo sportello con un documento.',
                'notifica' => 'Notificare il datore via PEC.',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $client->refresh();

        $this->assertSame('Richiedere il benestare all\'ufficio del personale.', $client->richiesta_benestare);
        $this->assertSame('Ritirare l\'atto allo sportello con un documento.', $client->ritiro_benestare);
        $this->assertSame('Notificare il datore via PEC.', $client->notifica);
    }

    private function makeClient(array $attributes = []): Client
    {
        return Client::query()->create(array_merge([
            'name' => 'Rossi',
            'first_name' => 'Mario',
            'is_person' => true,
            'tax_code' => strtoupper(Str::random(16)),
        ], $attributes));
    }

    private function assertBenestareColumns(): void
    {
        $rows = collect(DB::connection((new Client)->getConnectionName())->select(
            'select column_name, column_type, is_nullable, column_comment, ordinal_position
             from information_schema.columns
             where table_schema = database() and table_name = ?',
            ['clients'],
        ))->mapWithKeys(function (object $row): array {
            $name = strtolower((string) ($row->column_name ?? $row->COLUMN_NAME));

            return [$name => (object) [
                'column_type' => $row->column_type ?? $row->COLUMN_TYPE,
                'is_nullable' => $row->is_nullable ?? $row->IS_NULLABLE,
                'column_comment' => $row->column_comment ?? $row->COLUMN_COMMENT,
                'ordinal_position' => $row->ordinal_position ?? $row->ORDINAL_POSITION,
            ]];
        });

        $expected = [
            'richiesta_benestare' => 'Istruzioni per ottenere la richiesta di benestare presso questo datore',
            'ritiro_benestare' => "Istruzioni per il ritiro dell'atto di benestare",
            'notifica' => 'Istruzioni per la notifica al datore',
        ];

        foreach ($expected as $column => $comment) {
            $this->assertTrue($rows->has($column), "Manca la colonna clients.{$column}.");
            $this->assertSame('text', $rows[$column]->column_type);
            $this->assertSame('YES', $rows[$column]->is_nullable);
            $this->assertSame($comment, $rows[$column]->column_comment);
        }

        if (! $rows->has('hiring_date')) {
            return;
        }

        $hiringPosition = (int) $rows['hiring_date']->ordinal_position;

        $this->assertSame($hiringPosition + 1, (int) $rows['richiesta_benestare']->ordinal_position);
        $this->assertSame($hiringPosition + 2, (int) $rows['ritiro_benestare']->ordinal_position);
        $this->assertSame($hiringPosition + 3, (int) $rows['notifica']->ordinal_position);
    }
}
