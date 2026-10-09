<?php

namespace App\Filament\Agenti\Resources\Pratiche;

use App\Filament\Agenti\Resources\Pratiche\Pages\CreatePraticaAgente;
use App\Filament\Agenti\Resources\Pratiche\Pages\ListPraticheAgente;
use App\Filament\Agenti\Resources\Pratiche\Pages\ViewPraticaAgente;
use App\Filament\Agenti\Resources\Pratiche\RelationManagers\DocumentiFirmabiliRelationManager;
use App\Models\PROFORMA\Clienti;
use App\Models\PROFORMA\Fornitore;
use App\Models\PROFORMA\Pratica;
use App\Models\Tipoprodotto;
use App\Services\BlacklistChecker;
use BackedEnum;
use Closure;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
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

        return parent::getEloquentQuery()
            ->where('is_notowned', false)
            ->whereNull('rejected_at')
            ->whereIn('partita_iva_agente', $pivas === [] ? [''] : $pivas);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Cliente')
                ->columns(2)
                ->schema([
                    TextInput::make('nome_cliente')->label('Nome')->required()->maxLength(191),
                    TextInput::make('cognome_cliente')->label('Cognome')->required()->maxLength(191),
                    TextInput::make('codice_fiscale')->label('Codice fiscale')->required()->maxLength(16),
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
