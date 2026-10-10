<?php

namespace App\Models;

use Unico\Core\Enums\DocumentStatus;
use App\Events\TaskActivated;
use App\Models\PROFORMA\Pratica; // <-- Add this line!
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
/*
     * 1. GLOBAL SCOPE ISOLAMENTO E TASK COMUNI
     * Caricato automaticamente su tutte le query dell'applicazione.
*/
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\Model;
use Unico\Core\Models\Task as CoreTask;

class Task extends CoreTask implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $orderBy = 'name';

    protected $orderDirection = 'asc';

    /**
     * Get the parent taskable model (Project, User, etc.).
     */
    public function taskable(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function booted(): void
    {
        parent::booted();
        static::addGlobalScope('app_isolation', function (Builder $builder) {

            // Evita crash se esegui codice fuori dal contesto HTTP di Filament (es. php artisan db:seed)
            if (! app()->runningInConsole() && Filament::getCurrentPanel()) {
                $currentPanelId = Filament::getCurrentPanel()->getId();
                $currentApp = ($currentPanelId === 'admin') ? 'UnicoOAM' : 'UnicoFin';
            } else {
                // Valore di fallback generico se siamo in console o fuori da Filament
                // In questo modo legge tutto ciò che non è esplicitamente dell'altra app
                $currentApp = config('app.identifier', 'UnicoOAM');
            }

            // Il raggruppamento delle condizioni OR deve usare il Builder corretto
            $builder->where(function (Builder $query) use ($currentApp) {
                $query->where('app_identifier', $currentApp)
                    ->orWhereNull('app_identifier')
                    ->orWhere('app_identifier', '');
            });
        });
    }

    /**
     * I tipi di documento associati a questo Task
     */
    public function documentTypes()
    {
        return $this
            ->belongsToMany(DocumentType::class, 'document_requirements')
            ->using(TaskDocumentType::class)  // <-- Usa il nuovo modello Pivot
            ->withPivot('is_required')
            ->withTimestamps();
    }

    /**
     * Crea la documentazione mancante per questo specifico task.
     *
     * @param  int  $companyId  ID dell'azienda principale
     * @param  int  $documentableId  ID del record di destinazione (ID Azienda o ID Fornitore)
     * @param  bool  $is_debug  Abilita il debug
     * @return int Numero di documenti creati
     */
    public function createDocumentation(string $companyId, string $documentableId, bool $is_debug = false): int
    {
        $createdCount = 0;

        // Clicliamo sui documentTypes già caricati in memoria
        foreach ($this->documentTypes as $documentType) {
            // 1. Estraiamo SOLO i campi del template che la tabella 'documents' è in grado di accogliere
            $templateData = collect($documentType->toArray())
                ->only([
                    'name',
                    'description',
                    'emitted_by',
                    'is_template',
                    'is_monitored',
                    'doctype',
                ])
                ->toArray();
            // ** check trigger fields */
            if ($is_debug) {
                $emesso = now()->subDays(rand(3, 400));
                $templateData['emitted_at'] = $emesso;
                // 2. Set emitted_at for monitored documents
                if ($documentType->is_monitored) {
                    $scade = $documentType->durationCalculate($emesso);
                    $templateData['expires_at'] = $scade;
                }
            }
            // 3. Uniamo lo stato iniziale richiesto
            $creationData = array_merge($templateData, [
                'status' => DocumentStatus::PENDING->value,
            ]);

            // 4. Eseguiamo il firstOrCreate in sicurezza
            $document = Document::firstOrCreate(
                [
                    'company_id' => $companyId,
                    'documentable_type' => $this->taskable,
                    'documentable_id' => $documentableId,
                    'document_type_id' => $documentType->id,
                ],
                $creationData
            );

            if ($document->wasRecentlyCreated) {
                $createdCount++;
            }
        }

        return $createdCount;
    }

    /**
     * Campi del record che governano i plichi attivi del suo tipo (trigger, secondo campo ed esclusione).
     *
     * @return array<int, string>
     */
    public static function watchedFieldsFor(Model $record): array
    {
        return self::query()
            ->where('is_active', true)
            ->whereIn('taskable', array_unique([$record->getMorphClass(), strtolower(class_basename($record))]))
            ->get(['trigger_field', 'trigger_subfield', 'exclude_field'])
            ->flatMap(fn (Task $task): array => [$task->trigger_field, $task->trigger_subfield, $task->exclude_field])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Crea sul record i documenti mancanti di tutti i plichi applicabili al suo stato attuale.
     *
     * @return int numero di documenti creati
     */
    public static function applyPlichiTo(Model $record): int
    {
        return self::getAvailableFor($record)->load('documentTypes')->sum(fn (Task $task): int => $task->generateDocumentsFor($record)['created']);
    }

    /**
     * Crea sul record i documenti richiesti da questo plico che non ci sono ancora (stato "richiesto").
     * Non si tocca nulla di quanto esiste gia'.
     *
     * @return array{created: int, existing: int}
     */
    public function generateDocumentsFor(Model $owner): array
    {
        $created = 0;
        $existing = 0;

        foreach ($this->documentTypes as $documentType) {
            if ($owner->documents()->where('document_type_id', $documentType->getKey())->exists()) {
                $existing++;

                continue;
            }

            $owner->documents()->create(array_merge(
                collect($documentType->only(['name', 'description', 'emitted_by', 'is_template', 'is_monitored', 'doctype']))->all(),
                [
                    'document_type_id' => $documentType->getKey(),
                    'status' => DocumentStatus::PENDING->value,
                    // I proprietari letti da Proforma hanno un company_id UUID di quel database: non è un'azienda del pacchetto.
                    'company_id' => is_numeric($owner->company_id ?? null)
                        ? (int) $owner->company_id
                        : \App\Models\Company::query()->value('id'),
                ],
            ));
            $created++;
        }

        return ['created' => $created, 'existing' => $existing];
    }

    /**
     * 1. METODO ESISTENTE AGGIORNATO
     * Sfrutta il refactoring e pesca solo i task "Radice" (senza padre).
     */
    public static function getAvailableFor($record)
    {
        // taskable e' l'alias del morphMap ('client', 'pratica', 'fornitore'...) o, in alternativa, il nome breve del modello
        $taskableTypes = array_unique([$record->getMorphClass(), strtolower(class_basename($record))]);

        // Prendiamo solo i task attivi che NON hanno un padre (i task figli aspetteranno il loro turno)
        $rootTasks = self::whereIn('taskable', $taskableTypes)
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->get();

        // Filtriamo usando il nuovo metodo di istanza
        return $rootTasks->filter(function ($task) use ($record) {
            return $task->matchesConditions($record);
        });
    }

    /**
     * 2. NUOVO METODO: ATTIVAZIONE DEI TASK FIGLI
     * Da invocare quando il task corrente (padre) viene completato.
     */
    public function activateChildrenFor(Model $record): void
    {
        // Recuperiamo i soli figli attivi di questo task
        $children = $this->children()->where('is_active', true)->get();

        // Filtriamo i figli in base alle loro condizioni di trigger/esclusione sul record
        $activatableChildren = $children->filter(function ($childTask) use ($record) {
            return $childTask->matchesConditions($record);
        });

        foreach ($activatableChildren as $childTask) {
            // --- LOGICA DI ATTIVAZIONE ---
            // Esegui l'azione specifica della tua architettura, ad esempio:
            // - Creare un record nella tabella pivot dei task completabili per quel fornitore/utente
            // - Lanciare un evento di attivazione
            event(new TaskActivated($childTask, $record));
        }
    }

    /**
     * 3. LOGICA DI CONTROLLO CONDIZIONI (Estratta dal tuo metodo originale)
     * Verifica se il task è compatibile con lo stato attuale del record.
     */
    public function matchesConditions(Model $record): bool
    {
        // Verifica condizioni di esclusione
        if (! empty($this->exclude_field)) {
            $excludeValue = $record->{$this->exclude_field};

            if ($this->exclude_state === 'filled' && ! empty($excludeValue)) {
                return false;
            }

            if ($this->exclude_state === 'empty' && empty($excludeValue)) {
                return false;
            }

            if ($this->exclude_state === 'equals' && self::sameValue($excludeValue, $this->exclude_value)) {
                return false;
            }
        }

        return $this->matchesTrigger($record) && $this->matchesSubfield($record);
    }

    /**
     * Condizione principale (trigger_field): senza campo il task e' sempre valido.
     */
    private function matchesTrigger(Model $record): bool
    {
        if (empty($this->trigger_field)) {
            return true;
        }

        $fieldValue = $record->{$this->trigger_field};

        return match ($this->trigger_state) {
            'filled' => ! empty($fieldValue),
            'empty' => empty($fieldValue),
            'equals' => self::sameValue($fieldValue, $this->trigger_value),
            default => true,
        };
    }

    /**
     * Seconda condizione (trigger_subfield = trigger_subvalue), es. lo stato della pratica.
     */
    private function matchesSubfield(Model $record): bool
    {
        if (empty($this->trigger_subfield)) {
            return true;
        }

        return self::sameValue($record->{$this->trigger_subfield}, $this->trigger_subvalue);
    }

    /**
     * Confronto di valori testuali senza distinguere maiuscole/minuscole (es. "Mutuo" / "MUTUO").
     */
    private static function sameValue(mixed $actual, mixed $expected): bool
    {
        if ($actual instanceof \BackedEnum) {
            $actual = $actual->value;
        }

        // I booleani si confrontano come 1/0 (un campo falso vale "0", non stringa vuota).
        $actual = is_bool($actual) ? (int) $actual : $actual;

        return mb_strtolower(trim((string) $actual)) === mb_strtolower(trim((string) $expected));
    }

    /**
     * Relazione per i task figli
     */
    public function children(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id');
    }

    /**
     * Relazione per il task padre
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }
}
