<?php

namespace App\Models;

use App\Events\TaskActivated;
use Filament\Facades\Filament; // <-- Add this line!
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\MediaLibrary\HasMedia;
/*
     * 1. GLOBAL SCOPE ISOLAMENTO E TASK COMUNI
     * Caricato automaticamente su tutte le query dell'applicazione.
*/
use Spatie\MediaLibrary\InteractsWithMedia;

class Task extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $connection = 'mysql';

    protected $orderBy = 'name';

    protected $orderDirection = 'asc';

    protected $fillable = ['name', 'description', 'taskable', 'trigger_field', 'trigger_state', 'trigger_value', 'exclude_field', 'exclude_state',
        'exclude_value', 'is_active', 'parent_id', 'app_identifier'];

    /**
     * Get the parent taskable model (Project, User, etc.).
     */
    public function taskable(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function booted(): void
    {
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
            ->belongsToMany(DocumentType::class, 'task_document_types')
            ->using(TaskDocumentType::class)  // <-- Usa il nuovo modello Pivot
            ->withPivot('slug', 'is_required')
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
                    'is_signed',
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
                'status' => 'pending',
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
     * 1. METODO ESISTENTE AGGIORNATO
     * Sfrutta il refactoring e pesca solo i task "Radice" (senza padre).
     */
    public static function getAvailableFor($record)
    {
        $taskableType = strtolower(class_basename($record));

        // Prendiamo solo i task attivi che NON hanno un padre (i task figli aspetteranno il loro turno)
        $rootTasks = self::where('taskable', $taskableType)
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

            if ($this->exclude_state === 'equals' && $excludeValue == $this->exclude_value) {
                return false;
            }
        }

        // Se il task non ha condizioni di attivazione particolari, è valido
        if (empty($this->trigger_field)) {
            return true;
        }

        $fieldValue = $record->{$this->trigger_field};

        // Condizione: il campo deve essere valorizzato (NOT NULL)
        if ($this->trigger_state === 'filled') {
            return ! empty($fieldValue);
        }

        // Condizione: il campo deve essere vuoto (NULL)
        if ($this->trigger_state === 'empty') {
            return empty($fieldValue);
        }

        // Condizione: il campo deve essere uguale a un valore specifico
        if ($this->trigger_state === 'equals') {
            return $fieldValue == $this->trigger_value;
        }

        return true;
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
