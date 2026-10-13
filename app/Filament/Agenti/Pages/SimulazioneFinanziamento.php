<?php

namespace App\Filament\Agenti\Pages;

use App\Enums\EmploymentRelationship;
use App\Enums\SimulationContractType;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
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
     * @var array{tipo_contratto: string, sesso: string, tipo_rapporto: string, eta_decorrenza: int, anzianita_mesi: ?int, data_decorrenza: string}|null
     */
    public ?array $profilo = null;

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

                                    if ($relationship?->isRetiree() && ! self::contractTypeFrom($state)?->allowsRetirees()) {
                                        $set('tipo_rapporto', null);
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
                        ]),
                    Step::make('Inquadramento lavorativo e anzianità')
                        ->icon('heroicon-o-briefcase')
                        ->schema([
                            Select::make('tipo_rapporto')
                                ->label('Tipo rapporto')
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
                                ->required(),
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

        $relationship = self::relationshipFrom($state['tipo_rapporto']);
        $decorrenza = CarbonImmutable::parse($state['data_decorrenza']);
        $assunzione = filled($state['data_assunzione'] ?? null) ? CarbonImmutable::parse($state['data_assunzione']) : null;

        $this->profilo = [
            'tipo_contratto' => self::contractTypeFrom($state['tipo_contratto'])->getLabel(),
            'sesso' => $state['sesso'],
            'tipo_rapporto' => $relationship->getLabel(),
            'eta_decorrenza' => (int) CarbonImmutable::parse($state['data_nascita'])->diffInYears($decorrenza),
            'anzianita_mesi' => $assunzione ? (int) $assunzione->diffInMonths($decorrenza) : null,
            'data_decorrenza' => $decorrenza->format(self::DATE_FORMAT),
        ];
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
