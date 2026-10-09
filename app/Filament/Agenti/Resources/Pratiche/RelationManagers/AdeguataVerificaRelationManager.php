<?php

namespace App\Filament\Agenti\Resources\Pratiche\RelationManagers;

use App\Enums\KycActivityLocation;
use App\Enums\KycActivitySector;
use App\Enums\KycCompanyPurpose;
use App\Enums\KycEconomicActivity;
use App\Enums\KycFinancingNature;
use App\Enums\KycGeographicArea;
use App\Enums\KycIncomeBand;
use App\Enums\KycLegalNature;
use App\Enums\KycPepStatus;
use App\Enums\KycPersonPurpose;
use App\Enums\KycStatus;
use App\Enums\KycWealthBand;
use App\Filament\Agenti\Resources\Pratiche\Pages\ViewPraticaAgente;
use App\Models\KycQuestionnaire;
use App\Models\PROFORMA\Pratica;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Adeguata verifica compilata dal produttore: resta in bozza finche' non la invia all'istruttoria.
 * Livello di rischio, esecutore e titolari effettivi, approvazione e QAV restano all'istruttoria (pannello admin).
 */
class AdeguataVerificaRelationManager extends RelationManager
{
    protected static string $relationship = 'kycQuestionnaires';

    protected static ?string $title = 'Adeguata verifica';

    protected static ?string $modelLabel = 'adeguata verifica';

    protected static ?string $pluralModelLabel = 'adeguate verifiche';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $pageClass === ViewPraticaAgente::class;
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    private function isPerson(): bool
    {
        /** @var Pratica $pratica */
        $pratica = $this->getOwnerRecord();

        return (bool) $pratica->clienteAnagrafica()?->is_person;
    }

    public function form(Schema $schema): Schema
    {
        $isPerson = fn (): bool => $this->isPerson();

        return $schema->components([
            Section::make('Dati comuni')
                ->columns(2)
                ->schema([
                    Select::make('financing_purpose')
                        ->label('Scopo del finanziamento')
                        ->options(fn (): array => $this->purposeOptions())
                        ->required(),
                    Select::make('pep_status')
                        ->label('Condizione PEP')
                        ->options(KycPepStatus::class)
                        ->visible($isPerson)
                        ->required(),
                    Textarea::make('notes')->label('Note')->columnSpanFull(),
                ]),
            Section::make('Persona fisica')
                ->columns(2)
                ->visible($isPerson)
                ->schema([
                    Select::make('economic_activity')->label('Attività economica')->options(KycEconomicActivity::class)->required(),
                    Select::make('activity_sector')->label('Settore di attività')->options(KycActivitySector::class)->required(),
                    Select::make('activity_location')->label('Luogo di svolgimento')->options(KycActivityLocation::class)->required(),
                    Select::make('financing_nature')->label('Natura del finanziamento')->options(KycFinancingNature::class)->required(),
                    Select::make('income_band')->label('Reddito annuo lordo')->options(KycIncomeBand::class)->required(),
                    Select::make('wealth_band')->label('Patrimonio')->options(KycWealthBand::class)->required(),
                    Toggle::make('acts_for_third_party')
                        ->label('Agisce per conto di terzi')
                        ->helperText('I titolari effettivi vengono inseriti dall\'istruttoria.'),
                ]),
            Section::make('Persona giuridica')
                ->columns(2)
                ->visible(fn (): bool => ! $this->isPerson())
                ->schema([
                    Select::make('legal_nature')->label('Natura giuridica')->options(KycLegalNature::class)->required(),
                    Select::make('geographic_area')->label('Area geografica')->options(KycGeographicArea::class)->required(),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->emptyStateDescription(fn (): ?string => $this->getOwnerRecord()->clienteAnagrafica() === null
                ? 'Anagrafica del cliente non ancora presente: l\'istruttoria deve completarla prima della compilazione.'
                : null)
            ->columns([
                TextColumn::make('compiled_at')->label('Compilato il')->dateTime('d/m/Y'),
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->formatStateUsing(fn (?KycStatus $state): ?string => $state?->getLabel())
                    ->color(fn (?KycStatus $state): string => match ($state) {
                        KycStatus::Approved => 'success',
                        KycStatus::Complete => 'info',
                        default => 'gray',
                    }),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Compila adeguata verifica')
                    ->visible(fn (): bool => $this->canStart())
                    ->mutateDataUsing(function (array $data): array {
                        /** @var Pratica $pratica */
                        $pratica = $this->getOwnerRecord();

                        return $data + [
                            'client_id' => $pratica->clienteAnagrafica()?->getKey(),
                            'pratica_id' => $pratica->getKey(),
                            'status' => KycStatus::Draft->value,
                            'compiled_at' => now(),
                        ];
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Modifica')
                    ->visible(fn (KycQuestionnaire $record): bool => $record->status === KycStatus::Draft),
                Action::make('submit')
                    ->label('Invia all\'istruttoria')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (KycQuestionnaire $record): bool => $record->status === KycStatus::Draft)
                    ->action(function (KycQuestionnaire $record): void {
                        $missing = $this->missingForProducer($record);

                        if ($missing !== []) {
                            Notification::make()->danger()->title('Compilazione incompleta')->body('Mancano: '.implode(', ', $missing).'.')->send();

                            return;
                        }

                        $record->update(['status' => KycStatus::Complete, 'compiled_at' => now()]);

                        Notification::make()->success()->title('Inviata all\'istruttoria')->send();
                    }),
            ]);
    }

    /**
     * Si puo' iniziare solo con il cliente in anagrafica e senza altre compilazioni per questa pratica.
     */
    private function canStart(): bool
    {
        /** @var Pratica $pratica */
        $pratica = $this->getOwnerRecord();

        return $pratica->clienteAnagrafica() !== null && ! $pratica->kycQuestionnaires()->exists();
    }

    /**
     * Requisiti per l'invio: quelli dell'approvazione, tranne il livello di rischio che assegna l'istruttoria.
     *
     * @return array<int, string>
     */
    private function missingForProducer(KycQuestionnaire $record): array
    {
        $record = $record->fresh(['client', 'beneficialOwners']);

        return array_values(array_diff($record->missingRequirements(), [KycQuestionnaire::FIELD_LABELS['risk_level']]));
    }

    /**
     * @return array<string, string>
     */
    private function purposeOptions(): array
    {
        $enum = $this->isPerson() ? KycPersonPurpose::class : KycCompanyPurpose::class;

        return collect($enum::cases())
            ->mapWithKeys(fn ($case): array => [$case->value => $case->getLabel()])
            ->all();
    }
}
