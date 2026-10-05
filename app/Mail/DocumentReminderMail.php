<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

 // <-- Importante: permette di riconoscere l'oggetto Spatie

class DocumentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $bodyContent;

    public string $subjectLine;

    public array $fileAttachments;

    public function __construct(string $subjectLine, string $bodyContent, array $fileAttachments = [])
    {
        $this->subjectLine = $subjectLine;
        $this->bodyContent = $bodyContent;
        $this->fileAttachments = $fileAttachments; // Ora può ricevere stringhe o oggetti Media
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.blank',
            with: ['bodyContent' => $this->bodyContent]
        );
    }

    public function attachments(): array
    {
        $attachments = [];

        foreach ($this->fileAttachments as $file) {
            // 1. Se l'allegato è un oggetto Media di Spatie
            if ($file instanceof Media) {
                $path = $file->getPath();

                // Controllo di sicurezza: se il file è locale ed esiste sul server
                if (file_exists($path)) {
                    $attachments[] = Attachment::fromPath($path)
                        ->as($file->file_name)
                        ->withMime($file->mime_type);
                }
                // Se usi dischi cloud (S3, ecc.) o storage gestiti da Laravel
                elseif (Storage::disk($file->disk)->exists($file->getPathRelativeToRoot())) {
                    $attachments[] = Attachment::fromStorageDisk($file->disk, $file->getPathRelativeToRoot())
                        ->as($file->file_name)
                        ->withMime($file->mime_type);
                } else {
                    // Evita il crash: se il file non esiste, lo tracciamo nei log ma la mail parte comunque!
                    Log::warning("Impossibile allegare il file ID {$file->id}: file fisico non trovato.");
                }
            }
            // 2. Se è una stringa ed è un percorso file locale esistente (o URL valido tramite stream)
            elseif (is_string($file) && (file_exists($file) || filter_var($file, FILTER_VALIDATE_URL))) {
                $attachments[] = Attachment::fromPath($file);
            }
        }

        return $attachments;
    }
}
