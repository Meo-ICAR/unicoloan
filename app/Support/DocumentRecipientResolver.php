<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Document;
use App\Models\Employee;
use App\Models\PROFORMA\Clienti;
use App\Models\PROFORMA\Fornitore;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DocumentRecipientResolver
{
    /**
     * @return array{name: string, email: ?string}
     */
    public function resolve(Model $documentable): array
    {
        return match ($documentable::class) {
            Employee::class => [
                'name' => (string) $documentable->name,
                'email' => $documentable->email ?: $documentable->pec,
            ],
            Fornitore::class => [
                'name' => (string) ($documentable->nome ?: $documentable->name),
                'email' => $documentable->email ?: $documentable->email_private ?: $documentable->pec,
            ],
            Clienti::class => [
                'name' => (string) ($documentable->nome ?: $documentable->name),
                'email' => $documentable->email ?: $documentable->privacy_contact_email ?: $documentable->dpo_email,
            ],
            Company::class => [
                'name' => (string) $documentable->name,
                'email' => null,
            ],
            User::class => [
                'name' => (string) $documentable->name,
                'email' => $documentable->email,
            ],
            default => [
                'name' => $this->modelLabel($documentable),
                'email' => null,
            ],
        };
    }

    public function resolveForDocument(Document $document): array
    {
        $documentable = $document->documentable;

        if ($documentable === null) {
            return [
                'name' => 'Destinatario non disponibile',
                'email' => $this->fallbackEmail(),
            ];
        }

        $recipient = $this->resolve($documentable);

        if (blank($recipient['email'])) {
            $recipient['email'] = $this->fallbackEmail();
        }

        return $recipient;
    }

    public function groupLabel(Document $document): string
    {
        $documentable = $document->documentable;

        if ($documentable === null) {
            return $this->modelLabelFromType($document->documentable_type).' #'.$document->documentable_id;
        }

        $recipient = $this->resolve($documentable);

        return $this->modelLabelFromType($document->documentable_type).': '.$recipient['name'];
    }

    public function modelLabelFromType(?string $type): string
    {
        if ($type === null) {
            return 'Sconosciuto';
        }

        return match ($type) {
            Employee::class => 'Dipendente',
            Fornitore::class => 'Produttore',
            Clienti::class => 'Istituto',
            Company::class => 'Azienda',
            User::class => 'Utente',
            default => Str::headline(class_basename($type)),
        };
    }

    protected function modelLabel(Model $model): string
    {
        foreach (['name', 'title', 'protocol_number'] as $attribute) {
            if (! empty($model->{$attribute})) {
                return (string) $model->{$attribute};
            }
        }

        return $this->modelLabelFromType($model::class);
    }

    protected function fallbackEmail(): ?string
    {
        $configured = config('documents.reminder_fallback_email');

        if (filled($configured)) {
            return $configured;
        }

        return User::query()->orderBy('id')->value('email');
    }
}
