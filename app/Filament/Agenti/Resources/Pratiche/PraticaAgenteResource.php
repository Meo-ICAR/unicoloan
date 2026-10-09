<?php

namespace App\Filament\Agenti\Resources\Pratiche;

use App\Filament\Agenti\Resources\Pratiche\Pages\CreatePraticaAgente;
use App\Filament\Agenti\Resources\Pratiche\Pages\ListPraticheAgente;
use App\Filament\Agenti\Resources\Pratiche\Pages\ViewPraticaAgente;
use App\Filament\Agenti\Resources\Pratiche\RelationManagers\AdeguataVerificaRelationManager;
use App\Filament\Agenti\Resources\Pratiche\RelationManagers\DocumentiFirmabiliRelationManager;
use App\Models\PROFORMA\Clienti;
use App\Models\PROFORMA\Fornitore;
use App\Models\PROFORMA\Pratica;
use App\Models\Tipoprodotto;
use App\Services\Agenti\ClientForPraticaCreator;
use App\Services\BlacklistChecker;
use BackedEnum;
use Closure;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Pratiche dell'agente/produttore collegato all'utente: mai respinte, erogate al piu' da 45 giorni (per default).
 */
class PraticaAgenteResource extends Resource
{
    /**
     * Giorni dall'erogazione entro cui una pratica resta nell'elenco predefinito.
     */
    public const RECENT_EROGATION_DAYS = 45;

    protected static ?string $model = Pratica::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Pratiche';

    protected static ?string $modelLabel = 'Pratica';

    protected static ?string $pluralModelLabel = 'Pratiche';

    protected static ?string $slug = 'pratiche';

    protected static ?string $recordTitleAttribute = 'codice_pratica';

    public static function fornitore(): ?Fornitore
    {
        return Auth::user()?->fornitore;
    }

    /**
     * @return Builder<Pratica>
     */
    public static function getEloquentQuery(): Builder
    {
        $pivas = static::fornitore()?->visibleAgentPivas() ?? [];
        $query = parent::getEloquentQuery()->where('is_notowned', false)->whereNull('rejected_at');

        // Senza partita IVA l'agente non vede nulla (mai le pratiche con P.IVA vuota).
        return $pivas === [] ? $query->whereRaw('1 = 0') : $query->whereIn('partita_iva_agente', $pivas);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Cliente')
                ->columns(2)
                ->visibleOn('create')
                ->schema([
                    Radio::make('client_type')
                        ->label('Tipo cliente')
                        ->options(['person' => 'Persona fisica', 'company' => 'Persona giuridica'])
                        ->default('person')
                        ->inline()
                        ->live()
                        ->dehydrated(true)
                        ->columnSpanFull(),
                    TextInput::make('nome_cliente')->label('Nome')->required()->maxLength(191)
                        ->visible(fn (Get $get): bool => $get('client_type') !== 'company'),
                    TextInput::make('cognome_cliente')
                        ->label(fn (Get $get): string => $get('client_type') === 'company' ? 'Ragione sociale' : 'Cognome')
                        ->required()
                        ->maxLength(191),
                    TextInput::make('codice_fiscale')
                        ->label(fn (Get $get): string => $get('client_type') === 'company' ? 'Partita IVA' : 'Codice fiscale')
                        ->required()
                        ->rule(fn (Get $get): string => $get('client_type') === 'company' ? 'regex:/^\d{11}$/' : 'regex:/^[A-Za-z0-9]{16}$/')
                        ->rules([
                            fn (): Closure => function (string $attribute, $value, Closure $fail): void {
                                if (! app(ClientForPraticaCreator::class)->isUsableByAgent((string) $value, static::fornitore()?->piva)) {
                                    $fail('Cliente gia\' presente in anagrafica: contatta l\'istruttoria per collegare la pratica.');
                                }
                            },
                        ])
                        ->validationMessages(['regex' => 'Formato non valido.']),
                    TextInput::make('client_email')->label('Email del cliente')->email()->required()->maxLength(191),
                    TextInput::make('client_phone')->label('Cellulare del cliente (per l\'OTP)')->tel()->required()->maxLength(32),
                ]),
            Section::make('Cliente')
                ->columns(2)
                ->visibleOn('view')
                ->schema([
                    TextInput::make('cognome_cliente')->label('Cognome / Ragione sociale'),
                    TextInput::make('nome_cliente')->label('Nome'),
                    TextInput::make('codice_fiscale')->label('Codice fiscale / P.IVA'),
                ]),
            Section::make('Finanziamento')
                ->columns(2)
                ->schema([
                    Select::make('tipo_prodotto')
                        ->label('Tipo prodotto')
                        ->options(fn (): array => Tipoprodotto::query()->whereNotNull('tipo_prodotto')->orderBy('name')->pluck('tipo_prodotto', 'name')->all())
                        ->searchable()
                        ->required(),
                    Select::make('denominazione_banca')
                        ->label('Banca')
                        ->options(fn (): array => Clienti::query()->where('principal_type', 'banca')->whereNotNull('name')->orderBy('name')->pluck('name', 'name')->all())
                        ->searchable()
                        ->required()
                        ->rules([
                            fn (): Closure => function (string $attribute, $value, Closure $fail): void {
                                if (app(BlacklistChecker::class)->isAgenteNameBlacklistedForBancaName(static::fornitore()?->name, $value)) {
                                    $fail('Non puoi inserire pratiche per questa banca.');
                                }
                            },
                        ]),
                    TextInput::make('amount')->label('Importo')->numeric()->inputMode('decimal')->prefix('€')->minValue(0)->required(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('data_inserimento_pratica', 'desc')
            ->columns([
                TextColumn::make('cognome_cliente')->label('Cliente')->searchable()->sortable(),
                TextColumn::make('nome_cliente')->label('Nome')->searchable()->sortable(),
                TextColumn::make('tipo_prodotto')->label('Tipo prodotto')->sortable(),
                TextColumn::make('denominazione_banca')->label('Banca')->searchable()->sortable(),
                TextColumn::make('stato_pratica')->label('Stato')->badge()->sortable(),
                TextColumn::make('data_inserimento_pratica')->label('Inserita il')->date()->sortable(),
                TextColumn::make('erogated_at')->label('Erogata il')->date()->sortable(),
                TextColumn::make('codice_pratica')->label('Codice')->searchable(),
            ])
            ->filters([
                Filter::make('recent_erogation')
                    ->label('Non erogate o erogate da meno di '.self::RECENT_EROGATION_DAYS.' giorni')
                    ->default(true)
                    ->query(fn (Builder $query): Builder => $query->where(
                        fn (Builder $inner) => $inner->whereNull('erogated_at')
                            ->orWhere('erogated_at', '>=', now()->subDays(self::RECENT_EROGATION_DAYS)->startOfDay()),
                    )),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AdeguataVerificaRelationManager::class,
            DocumentiFirmabiliRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPraticheAgente::route('/'),
            'create' => CreatePraticaAgente::route('/create'),
            'view' => ViewPraticaAgente::route('/{record}'),
        ];
    }
}
