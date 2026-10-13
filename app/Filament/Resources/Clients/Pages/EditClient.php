<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Enums\FinancingSection;
use App\Enums\FinancingType;
use App\Filament\Actions\CreaMandatoPraticaAction;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Clients\RelationManagers\ClientRelationsRelationManager;
use App\Filament\Resources\Clients\RelationManagers\ImpegniTerziRelationManager;
use App\Filament\Resources\Praticas\PraticaResource;
use App\Filament\Resources\RelationManagers\BranchesRelationManager;
use App\Filament\Resources\RelationManagers\WebsitesRelationManager;
use App\Models\Pratica;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    /**
     * Pratica da cui e' stata aperta la scheda: ne determina il tipo di finanziamento e quindi i dati mostrati.
     */
    #[Url]
    public ?string $pratica = null;

    /**
     * @var array<class-string, FinancingSection>
     */
    private const RELATION_MANAGER_SECTIONS = [
        ClientRelationsRelationManager::class => FinancingSection::ClienteCaricheSociali,
        WebsitesRelationManager::class => FinancingSection::ClienteSediSiti,
        BranchesRelationManager::class => FinancingSection::ClienteSediSiti,
        ImpegniTerziRelationManager::class => FinancingSection::ClienteImpegniTerzi,
    ];

    /**
     * La pratica conta solo se e' del cliente aperto (stesso codice fiscale o partita IVA).
     */
    public function contextPratica(): ?Pratica
    {
        return once(function (): ?Pratica {
            if (! ctype_digit((string) $this->pratica)) {
                return null;
            }

            $pratica = Pratica::query()->find((int) $this->pratica);
            $code = Str::upper(trim((string) $pratica?->codice_fiscale));
            $client = $this->getRecord();

            if ($code === '' || ! in_array($code, [Str::upper((string) $client->tax_code), Str::upper((string) $client->vat_number)], true)) {
                return null;
            }

            return $pratica;
        });
    }

    public function financingType(): ?FinancingType
    {
        $pratica = $this->contextPratica();

        return $pratica === null ? null : FinancingType::forPratica($pratica);
    }

    public function showsFinancingSection(FinancingSection $section): bool
    {
        return $section->isVisibleFor($this->financingType());
    }

    public function getRelationManagers(): array
    {
        return array_filter(
            parent::getRelationManagers(),
            fn ($manager): bool => ! is_string($manager)
                || ! isset(self::RELATION_MANAGER_SECTIONS[$manager])
                || $this->showsFinancingSection(self::RELATION_MANAGER_SECTIONS[$manager]),
        );
    }

    public function getSubheading(): ?string
    {
        $type = $this->financingType();

        if ($type === null) {
            return null;
        }

        return "Vista per la pratica {$this->contextPratica()->codice_pratica} · {$type->getLabel()}: sono mostrati solo i dati pertinenti.";
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('torna_pratica')
                ->label('Torna alla pratica')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->visible(fn (): bool => $this->contextPratica() !== null)
                ->url(fn (): string => PraticaResource::getUrl('edit', ['record' => $this->contextPratica()])),
            Action::make('mostra_tutto')
                ->label('Mostra tutti i dati')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->visible(fn (): bool => $this->financingType() !== null)
                ->url(fn (): string => ClientResource::getUrl('edit', ['record' => $this->getRecord()])),
            CreaMandatoPraticaAction::make(),
            DeleteAction::make(),
        ];
    }
}
