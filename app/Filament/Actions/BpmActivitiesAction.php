<?php

namespace App\Filament\Actions;

use App\Services\BpmActivitiesClient;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * Azione riutilizzabile per le risorse Filament: chiede a UnicoBPM l'elenco
 * delle attività (processi) avviabili sul record corrente, passando i valori
 * dei suoi campi perché UnicoBPM valuti i propri criteri senza dover leggere
 * le tabelle di questa app.
 *
 * Uso su una risorsa: `BpmActivitiesAction::make()->modelType('fornitore')`.
 * `modelType` deve corrispondere a una chiave del morphMap configurato lato
 * UnicoBPM (es. 'fornitore', 'cliente', 'employee').
 */
class BpmActivitiesAction extends Action
{
    protected string $bpmModelType;

    public static function getDefaultName(): ?string
    {
        return 'bpmAttivita';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Attività')
            ->icon('heroicon-o-bolt')
            ->color('gray')
            ->modalHeading('Attività disponibili')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Chiudi')
            ->modalContent(function (Model $record): HtmlString {
                $activities = app(BpmActivitiesClient::class)->availableFor(
                    $this->bpmModelType,
                    (string) $record->getKey(),
                    $record->toArray(),
                );

                if (empty($activities)) {
                    return new HtmlString('<p>Nessuna attività disponibile per questo record.</p>');
                }

                $items = collect($activities)
                    ->map(fn (array $activity) => '<li><strong>'.e($activity['name'] ?? $activity['code'] ?? '').'</strong>'
                        .(! empty($activity['description']) ? ' — '.e($activity['description']) : '').'</li>')
                    ->implode('');

                return new HtmlString("<ul class=\"list-disc pl-5 space-y-1\">{$items}</ul>");
            });
    }

    public function modelType(string $modelType): static
    {
        $this->bpmModelType = $modelType;

        return $this;
    }
}
