<?php

namespace App\Filament\Actions;

use App\Models\Client;
use App\Models\Document;
use App\Models\PdfModule;
use App\Models\PROFORMA\Pratica;
use App\Services\ModuleDataResolver;
use App\Services\ModuleSuggester;
use App\Services\PdfFormException;
use App\Services\PraticaModuleGenerator;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;

/**
 * Genera i moduli PDF compilati per la pratica corrente e li archivia tra i suoi documenti.
 */
class GeneraModuliPraticaAction extends Action
{
    /**
     * @var array<string, Client|null>
     */
    private array $clients = [];

    public static function getDefaultName(): ?string
    {
        return 'generaModuli';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Genera moduli')
            ->icon('heroicon-o-document-text')
            ->color('primary')
            ->modalHeading('Genera moduli PDF')
            ->modalDescription('Scegli i moduli da compilare con i dati della pratica e del cliente.')
            ->modalSubmitActionLabel('Genera')
            ->hidden(fn (Pratica $record): bool => (bool) $record->is_notowned)
            ->disabled(fn (Pratica $record): bool => $this->clientFor($record) === null)
            ->tooltip(fn (Pratica $record): ?string => $this->clientFor($record) === null
                ? 'Cliente non trovato per il codice fiscale della pratica'
                : null)
            ->schema(fn (Pratica $record): array => $this->formSchema($record))
            ->action(fn (Pratica $record, array $data) => $this->generate($record, $data['moduli'] ?? []));
    }

    private function clientFor(Pratica $pratica): ?Client
    {
        return $this->clients[$pratica->getKey()] ??= app(ModuleDataResolver::class)->findClient($pratica);
    }

    /**
     * @return array<int, CheckboxList>
     */
    private function formSchema(Pratica $pratica): array
    {
        $client = $this->clientFor($pratica);

        if ($client === null) {
            return [];
        }

        $modules = PdfModule::query()->active()->with('fields')->orderBy('name')->get();
        $missing = app(PraticaModuleGenerator::class)->missingByModule($pratica, $client, $modules);

        return [
            CheckboxList::make('moduli')
                ->label('Moduli')
                ->options($modules->pluck('name', 'id')->all())
                ->descriptions($modules->mapWithKeys(fn (PdfModule $module) => [
                    $module->getKey() => $missing[$module->getKey()] === []
                        ? 'Dati completi'
                        : 'Dati mancanti: '.collect($missing[$module->getKey()])->map->getLabel()->implode(', '),
                ])->all())
                ->default(app(ModuleSuggester::class)->suggest($pratica, $client)->pluck('id')->all())
                ->required()
                ->columns(1),
        ];
    }

    /**
     * @param  array<int, int|string>  $moduleIds
     */
    private function generate(Pratica $pratica, array $moduleIds): void
    {
        $client = $this->clientFor($pratica);

        if ($client === null) {
            Notification::make()
                ->title('Cliente non trovato')
                ->body('Nessun cliente corrisponde al codice fiscale della pratica.')
                ->danger()
                ->send();

            return;
        }

        $modules = PdfModule::query()->active()->whereKey($moduleIds)->with('fields')->orderBy('name')->get();

        try {
            $documents = app(PraticaModuleGenerator::class)->generate($pratica, $client, $modules, auth()->user());
        } catch (PdfFormException $exception) {
            report($exception);

            Notification::make()
                ->title('Compilazione non disponibile')
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title($documents->count() === 1 ? 'Modulo generato' : $documents->count().' moduli generati')
            ->body('I PDF sono stati salvati tra i documenti della pratica.')
            ->success()
            ->persistent()
            ->actions($documents->map(fn (Document $document) => Action::make('scarica_'.$document->getKey())
                ->label($document->name)
                ->button()
                ->url(route('documents.download', $document), shouldOpenInNewTab: true))->all())
            ->send();
    }
}
