<?php

namespace App\Filament\Agenti\Pages;

use App\Enums\EmploymentRelationship;
use App\Enums\SimulationContractType;
use App\Models\User;
use App\Services\Agenti\FinancingQuoteRecorder;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/**
 * Wizard con cui l'agente profila il cliente per una simulazione di cessione, delega o prestito.
 *
 * @property-read Schema $form
 */
class SimulazioneFinanziamento extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'Simulazione finanziamento';

    protected static ?string $title = 'Simulazione finanziamento';

    protected static ?string $slug = 'simulazione-finanziamento';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.agenti.pages.simulazione-finanziamento';

    private const string DATE_FORMAT = 'm-d-Y';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * @var array{tipo_contratto: string, sesso: string, tipo_rapporto: string, eta_decorrenza: int, anzianita_mesi: ?int, data_decorrenza: string, durata_mesi: int, durata_etichetta: string, quota_cedibile: ?float, importo_richiesto: ?float}|null
     */
    public ?array $profilo = null;

    /**
     * Dati grezzi dell'ultima simulazione, usati per memorizzare il preventivo.
     *
     * @var array<string, mixed>|null
     */
    public ?array $bozza = null;

    public ?int $preventivoIdentificativo = null;

    public bool $preventivoDeciso = false;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make('Dati cliente')
                        ->icon('heroicon-o-user')
                        ->schema([
                            ToggleButtons::make('tipo_contratto')
                                ->label('Tipo contratto')
                                ->options(SimulationContractType::class)
                                ->inline()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                                    $relationship = self::relationshipFrom($get('tipo_rapporto'));
                                    $contract = self::contractTypeFrom($state);

                                    if ($relationship?->isRetiree() && $contract?->allowsRetirees() === false) {
                                        $set('tipo_rapporto', null);
                                    }

                                    if ($contract?->asksQuotaCedibile() === true) {
                                        $set('importo_richiesto', null);
                                    } else {
                                        $set('quota_cedibile', null);
                                    }

                                    if ($contract !== null && ! in_array((int) $get('durata'), $contract->durationChoices(), true)) {
                                        $set('durata', $contract->defaultDuration());
                                    }
                                }),
                            ToggleButtons::make('sesso')
                                ->label('Sesso')
                                ->options(['M' => 'M', 'F' => 'F'])
                                ->inline()
                                ->required(),
                            DatePicker::make('data_nascita')
                                ->label('Data di nascita')
                                ->native(false)
                                ->displayFormat(self::DATE_FORMAT)
                                ->placeholder('MM-GG-AAAA')
                                ->maxDate(today()->subYears(18))
                                ->required(),
                            Select::make('tipo_rapporto')
                                ->label('Tipo amministrazione')
                                ->options(fn (Get $get): array => EmploymentRelationship::groupedOptions(
                                    self::contractTypeFrom($get('tipo_contratto')),
                                ))
                                ->in(fn (Get $get): array => array_column(
                                    EmploymentRelationship::allowedFor(self::contractTypeFrom($get('tipo_contratto'))),
                                    'value',
                                ))
                                ->helperText(fn (Get $get): ?string => self::contractTypeFrom($get('tipo_contratto'))?->allowsRetirees() === false
                                    ? 'La delega è riservata ai dipendenti.'
                                    : null)
                                ->native(false)
                                ->required()
                                ->live(),
                            DatePicker::make('data_assunzione')
                                ->label('Data assunzione')
                                ->native(false)
                                ->displayFormat(self::DATE_FORMAT)
                                ->placeholder('MM-GG-AAAA')
                                ->maxDate(today())
                                ->after('data_nascita')
                                ->visible(fn (Get $get): bool => self::relationshipFrom($get('tipo_rapporto'))?->isRetiree() === false)
                                ->required(fn (Get $get): bool => self::relationshipFrom($get('tipo_rapporto'))?->isRetiree() === false),
                        ]),
                    Step::make('Durata e importo')
                        ->icon('heroicon-o-banknotes')
                        ->schema([
                            Select::make('durata')
                                ->label('Durata')
                                ->options(fn (Get $get): array => self::contractTypeFrom($get('tipo_contratto'))?->durationOptions() ?? [])
                                ->in(fn (Get $get): array => self::contractTypeFrom($get('tipo_contratto'))?->durationChoices() ?? [])
                                ->native(false)
                                ->required(),
                            TextInput::make('quota_cedibile')
                                ->label('Quota cedibile')
                                ->numeric()
                                ->inputMode('decimal')
                                ->prefix('€')
                                ->minValue(0)
                                ->visible(fn (Get $get): bool => self::contractTypeFrom($get('tipo_contratto'))?->asksQuotaCedibile() === true)
                                ->required(fn (Get $get): bool => self::contractTypeFrom($get('tipo_contratto'))?->asksQuotaCedibile() === true),
                            TextInput::make('importo_richiesto')
                                ->label('Importo richiesto')
                                ->numeric()
                                ->inputMode('decimal')
                                ->prefix('€')
                                ->minValue(0)
                                ->visible(fn (Get $get): bool => self::contractTypeFrom($get('tipo_contratto'))?->asksQuotaCedibile() === false)
                                ->required(fn (Get $get): bool => self::contractTypeFrom($get('tipo_contratto'))?->asksQuotaCedibile() === false),
                            DatePicker::make('data_decorrenza')
                                ->label('Data decorrenza')
                                ->native(false)
                                ->displayFormat(self::DATE_FORMAT)
                                ->placeholder('MM-GG-AAAA')
                                ->default(fn (): string => today()->addMonthsNoOverflow(3)->toDateString())
                                ->minDate(today())
                                ->helperText('Proposta a 3 mesi dalla data odierna.')
                                ->required(),
                        ]),
                ])
                    ->submitAction(new HtmlString(Blade::render(<<<'BLADE'
                        <x-filament::button type="submit" icon="heroicon-o-calculator">
                            Calcola profilo
                        </x-filament::button>
                    BLADE))),
            ])
            ->statePath('data');
    }

    public function simulate(): void
    {
        $state = $this->form->getState();

        $contract = self::contractTypeFrom($state['tipo_contratto']);
        $relationship = self::relationshipFrom($state['tipo_rapporto']);
        $decorrenza = CarbonImmutable::parse($state['data_decorrenza']);
        $assunzione = filled($state['data_assunzione'] ?? null) ? CarbonImmutable::parse($state['data_assunzione']) : null;

        $eta = (int) CarbonImmutable::parse($state['data_nascita'])->diffInYears($decorrenza);
        $anzianita = $assunzione ? (int) $assunzione->diffInMonths($decorrenza) : null;
        $quota = $contract->asksQuotaCedibile() ? (float) $state['quota_cedibile'] : null;
        $importo = $contract->asksQuotaCedibile() ? null : (float) $state['importo_richiesto'];

        $this->profilo = [
            'tipo_contratto' => $contract->getLabel(),
            'sesso' => $state['sesso'],
            'tipo_rapporto' => $relationship->getLabel(),
            'eta_decorrenza' => $eta,
            'anzianita_mesi' => $anzianita,
            'data_decorrenza' => $decorrenza->format(self::DATE_FORMAT),
            'durata_mesi' => $contract->durationInMonths((int) $state['durata']),
            'durata_etichetta' => $contract->durationLabel((int) $state['durata']),
            'quota_cedibile' => $quota,
            'importo_richiesto' => $importo,
        ];
        $this->bozza = [
            'contract_type' => $contract->value,
            'sex' => $state['sesso'],
            'birth_date' => CarbonImmutable::parse($state['data_nascita'])->toDateString(),
            'employment_relationship' => $relationship->value,
            'hired_at' => $assunzione?->toDateString(),
            'starts_at' => $decorrenza->toDateString(),
            'duration_months' => $contract->durationInMonths((int) $state['durata']),
            'assignable_quota' => $quota,
            'requested_amount' => $importo,
            'age_at_start' => $eta,
            'seniority_months' => $anzianita,
        ];
        $this->preventivoIdentificativo = null;
        $this->preventivoDeciso = false;
    }

    public function memorizzaPreventivo(FinancingQuoteRecorder $recorder): void
    {
        $user = Auth::user();
        $fornitore = $user instanceof User ? $user->fornitore : null;

        if ($this->bozza === null || $this->preventivoDeciso || ! $user instanceof User || $fornitore === null) {
            return;
        }

        $quote = $recorder->store($fornitore, $this->bozza, $user);

        $this->preventivoIdentificativo = $quote->number;
        $this->preventivoDeciso = true;
    }

    public function scartaPreventivo(): void
    {
        $this->preventivoDeciso = true;
        $this->preventivoIdentificativo = null;
    }

    private static function contractTypeFrom(mixed $state): ?SimulationContractType
    {
        return $state instanceof SimulationContractType ? $state : SimulationContractType::tryFrom((string) $state);
    }

    private static function relationshipFrom(mixed $state): ?EmploymentRelationship
    {
        return $state instanceof EmploymentRelationship ? $state : EmploymentRelationship::tryFrom((string) $state);
    }
}
