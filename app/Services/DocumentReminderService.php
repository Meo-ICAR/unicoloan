<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Mail\DocumentReminderMail;
use App\Models\Document;
use App\Models\DocumentReminder;
use App\Models\EmailTemplate;
use App\Support\DocumentRecipientResolver;
use App\Support\EmailTemplateRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class DocumentReminderService
{
    public function __construct(
        protected DocumentRecipientResolver $recipientResolver,
        protected EmailTemplateRenderer $templateRenderer,
    ) {}

    public function scheduleQuery(): Builder
    {
        $windowDays = (int) config('documents.schedule_window_days', 90);
        $until = now()->addDays($windowDays)->toDateString();

        return Document::query()
            ->with(['documentType', 'documentable'])
            ->where(function (Builder $query) use ($until) {
                // Opzione 1: Tutte le tue regole attuali raggruppate
                $query->where(function (Builder $subQuery) use ($until) {
                    $subQuery->where('is_monitored', true)
                        ->whereNotNull('expires_at')
                        ->where('expires_at', '<=', $until)
                        ->whereNotIn('status', [
                            DocumentStatus::REJECTED->value,
                            DocumentStatus::NA->value,
                        ])
                        ->where(function (Builder $reminderQuery) {
                            $reminderQuery->whereNull('last_sent_at')
                                ->orWhere('last_sent_at', '<', now()->subDays(5));
                        });
                })
                // Opzione 2: OPPURE qualsiasi documento che sia semplicemente PENDING
                    ->orWhere('status', DocumentStatus::PENDING->value);
            })
            ->orderBy('expires_at');
    }

    /**
     * @return Collection<int, Collection<int, Document>>
     */
    public function groupedDocuments(?Builder $query = null): Collection
    {
        $documents = ($query ?? $this->scheduleQuery())->get();

        return $documents->groupBy(
            fn (Document $document): string => $document->documentable_type.'|'.$document->documentable_id
        );
    }

    /**
     * @return array{sent: int, skipped: int, failed: int, groups: int}
     */
    public function sendReminders(bool $onlyDueToday = true, ?string $groupKey = null): array
    {
        $template = EmailTemplate::query()
            ->where('code', 'DOC_EXPIRING')
            ->where('is_active', true)
            ->first();

        if ($template === null) {
            return ['sent' => 0, 'skipped' => 0, 'failed' => 0, 'groups' => 0];
        }

        $stats = ['sent' => 0, 'skipped' => 0, 'failed' => 0, 'groups' => 0];

        $groups = $this->groupedDocuments();

        if ($groupKey !== null) {
            $groups = $groups->only([$groupKey]);
        }

        foreach ($groups as $documents) {
            $dueDocuments = $documents
                ->filter(fn (Document $document): bool => $this->shouldRemind($document, $onlyDueToday))
                ->values();

            if ($dueDocuments->isEmpty()) {
                $stats['skipped'] += $documents->count();

                continue;
            }

            $stats['groups']++;

            $recipient = $this->recipientResolver->resolveForDocument($dueDocuments->first());

            if (blank($recipient['email'])) {
                $stats['skipped'] += $dueDocuments->count();

                continue;
            }

            try {
                $rendered = $this->renderGroupEmail($template, $recipient['name'], $dueDocuments);

                Mail::to($recipient['email'])->send(
                    new DocumentReminderMail($rendered['subject'], $rendered['body'])
                );

                foreach ($dueDocuments as $document) {
                    $this->recordReminder($document, $this->daysUntilExpiry($document), $recipient['email']);
                    $stats['sent']++;
                }
            } catch (\Throwable $exception) {
                foreach ($dueDocuments as $document) {
                    $this->recordFailedReminder(
                        $document,
                        $this->daysUntilExpiry($document),
                        $recipient['email'],
                        $exception->getMessage()
                    );
                    $stats['failed']++;
                }
            }
        }

        return $stats;
    }

    public function shouldRemind(Document $document, bool $onlyDueToday = true): bool
    {
        $daysUntilExpiry = $this->daysUntilExpiry($document);

        if ($onlyDueToday) {
            if (! in_array($daysUntilExpiry, $this->notifyThresholds($document), true)) {
                return false;
            }

            return ! $this->reminderAlreadySent($document, $daysBefore = $daysUntilExpiry);
        }

        return true;
    }

    public function daysUntilExpiry(Document $document): int
    {
        return (int) now()->startOfDay()->diffInDays($document->expires_at?->startOfDay(), false);
    }

    /**
     * @return array<int>
     */
    public function notifyThresholds(Document $document): array
    {
        $configured = $document->documentType?->notify_days_before;

        if (is_array($configured) && $configured !== []) {
            return array_map('intval', $configured);
        }

        return array_map('intval', config('documents.default_notify_days_before', [30, 15, 7, 1, 0]));
    }

    /**
     * @param  Collection<int, Document>  $documents
     * @return array{subject: string, body: string}
     */
    protected function renderGroupEmail(EmailTemplate $template, string $recipientName, Collection $documents): array
    {
        $listItems = $documents
            ->map(function (Document $document): string {
                $status = $this->daysUntilExpiry($document) < 0 ? 'SCADUTO' : $document->expires_at?->format('d/m/Y');

                return sprintf(
                    '<li><strong>%s</strong> (%s) — scadenza: %s</li>',
                    e($document->name),
                    e($document->documentType?->name ?? 'Documento'),
                    e((string) $status)
                );
            })
            ->implode('');

        $firstDocument = $documents->first();

        $rendered = $this->templateRenderer->render($template, [
            '{agente_nome}' => $recipientName,
            '{documento_nome}' => $documents->count() === 1
                ? (string) $firstDocument?->name
                : $documents->count().' documenti',
            '{data_scadenza}' => $documents->count() === 1
                ? ($firstDocument?->expires_at?->format('d/m/Y') ?? '—')
                : 'vedi elenco',
        ]);

        if (! Str::contains($rendered['body'], '{elenco_documenti}')) {
            $rendered['body'] .= '<ul>'.$listItems.'</ul>';
        } else {
            $rendered['body'] = str_replace('{elenco_documenti}', '<ul>'.$listItems.'</ul>', $rendered['body']);
        }

        if ($documents->count() > 1) {
            $rendered['subject'] = 'Sollecito scadenze documenti ('.$documents->count().')';
        }

        return $rendered;
    }

    protected function reminderAlreadySent(Document $document, int $daysBefore): bool
    {
        return DocumentReminder::query()
            ->where('document_id', $document->id)
            ->where('days_before', $daysBefore)
            ->where('status', 'sent')
            ->exists();
    }

    protected function recordReminder(Document $document, int $daysBefore, string $email): void
    {
        // 1. Registra lo storico del reminder inviato
        DocumentReminder::query()->updateOrCreate(
            [
                'document_id' => $document->id,
                'days_before' => $daysBefore,
            ],
            [
                'recipient_email' => $email,
                'status' => 'sent',
                'error_message' => null,
                'sent_at' => now(),
            ]
        );

        // 2. Aggiorna contatori e stato sul documento principale in una sola query atomica
        $document->update([
            'reminders_count' => $document->reminders_count + 1,
            'last_sent_at' => now(),
            'status' => DocumentStatus::PROVISIONAL->value, // Impostato a provvisorio/in attesa dopo il sollecito
        ]);
    }

    protected function recordFailedReminder(Document $document, int $daysBefore, string $email, string $message): void
    {
        DocumentReminder::query()->updateOrCreate(
            [
                'document_id' => $document->id,
                'days_before' => $daysBefore,
            ],
            [
                'recipient_email' => $email,
                'status' => 'failed',
                'error_message' => $message,
                'sent_at' => now(),
            ]
        );
    }
}
