<?php

namespace Tests\Feature;

use App\Mail\DocumentReminderMail;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentReminder;
use App\Models\DocumentType;
use App\Models\EmailTemplate;
use App\Models\Employee;
use App\Services\DocumentReminderService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentReminderServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_groups_documents_by_documentable_and_sends_one_email(): void
    {
        Mail::fake();

        EmailTemplate::query()->create([
            'code' => 'DOC_EXPIRING',
            'name' => 'Promemoria',
            'subject' => 'Scadenza {documento_nome}',
            'body' => '<p>Ciao {agente_nome}</p>{elenco_documenti}',
            'placeholders' => ['{agente_nome}', '{documento_nome}', '{data_scadenza}', '{elenco_documenti}'],
            'is_active' => true,
        ]);

        $company = Company::factory()->create();
        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'name' => 'Mario Rossi',
            'email' => 'dipendente@example.com',
        ]);

        $documentType = DocumentType::query()->create([
            'name' => 'Patente',
            'slug' => 'patente-test',
            'priority' => 1,
            'notify_days_before' => [7],
        ]);

        $sharedAttributes = [
            'company_id' => $company->id,
            'documentable_type' => Employee::class,
            'documentable_id' => (string) $employee->id,
            'document_type_id' => $documentType->id,
            'status' => 'verified',
            'is_monitored' => true,
            'expires_at' => now()->addDays(7)->toDateString(),
        ];

        Document::query()->create(array_merge($sharedAttributes, [
            'id' => (string) Str::uuid(),
            'name' => 'Patente A',
        ]));

        Document::query()->create(array_merge($sharedAttributes, [
            'id' => (string) Str::uuid(),
            'name' => 'Patente B',
        ]));

        $stats = app(DocumentReminderService::class)->sendReminders(onlyDueToday: true);

        $this->assertSame(1, $stats['groups']);
        $this->assertSame(2, $stats['sent']);

        Mail::assertSent(DocumentReminderMail::class, function (DocumentReminderMail $mail): bool {
            return $mail->hasTo('dipendente@example.com');
        });

        $this->assertSame(2, DocumentReminder::query()->where('status', 'sent')->count());
    }

    public function test_does_not_resend_same_threshold_reminder(): void
    {
        Mail::fake();

        EmailTemplate::query()->create([
            'code' => 'DOC_EXPIRING',
            'name' => 'Promemoria',
            'subject' => 'Scadenza',
            'body' => '{elenco_documenti}',
            'placeholders' => ['{elenco_documenti}'],
            'is_active' => true,
        ]);

        $company = Company::factory()->create();
        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'name' => 'Mario Rossi',
            'email' => 'dipendente@example.com',
        ]);

        $document = Document::query()->create([
            'id' => (string) Str::uuid(),
            'company_id' => $company->id,
            'documentable_type' => Employee::class,
            'documentable_id' => (string) $employee->id,
            'name' => 'Documento unico',
            'status' => 'verified',
            'is_monitored' => true,
            'expires_at' => now()->addDays(7)->toDateString(),
        ]);

        $service = app(DocumentReminderService::class);

        $first = $service->sendReminders(onlyDueToday: true);
        $second = $service->sendReminders(onlyDueToday: true);

        $this->assertSame(1, $first['sent']);
        $this->assertSame(0, $second['sent']);
        Mail::assertSent(DocumentReminderMail::class, 1);
    }
}
