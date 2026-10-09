<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Enums\KycControlCriterion;
use App\Enums\KycExecutorLink;
use App\Enums\KycPepStatus;
use App\Enums\KycRiskLevel;
use App\Enums\KycStatus;
use App\Filament\Actions\SendForSignatureAction;
use App\Filament\Resources\Clients\Schemas\KycQuestionnaireForm;
use App\Models\KycQuestionnaire;
use App\Services\Kyc\BeneficialOwnerSuggester;
use App\Services\Kyc\KycApprover;
use App\Services\Kyc\KycQavGenerator;
use App\Services\PdfFormException;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class KycQuestionnairesRelationManager extends RelationManager
{
    protected static string $relationship = 'kycQuestionnaires';

    protected static ?string $title = 'KYC (adeguata verifica)';

    protected static ?string $modelLabel = 'compilazione KYC';

    protected static ?string $pluralModelLabel = 'compilazioni KYC';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function form(Schema $schema): Schema
    {
        $isPerson = fn (): bool => (bool) $this->getOwnerRecord()->is_person;

        return $schema
            ->components([
                Section::make('Dati comuni')
                    ->columns(2)
                    ->schema([
                        KycQuestionnaireForm::pepStatus($isPerson),
                        KycQuestionnaireForm::financingPurpose($isPerson),
                        KycQuestionnaireForm::riskLevel(),
                        Select::make('client_mandate_id')
                            ->label('Mandato')
                            ->options(fn (): array => $this->getOwnerRecord()->clientMandates()->pluck('numero_mandato', 'id')->all())
                            ->searchable(),
                        DateTimePicker::make('compiled_at')
                            ->label('Compilato il')
                            ->default(now()),
                        Textarea::make('notes')
                            ->label('Note')
                            ->columnSpanFull(),
                    ]),
                KycQuestionnaireForm::personSection($isPerson, liveThirdParty: true),
                Section::make('Persona giuridica')
                    ->columns(2)
                    ->visible(fn (): bool => ! $this->getOwnerRecord()->is_person)
                    ->schema([
                        ...KycQuestionnaireForm::companyFields(),
                        Select::make('executor_client_id')
                            ->label('Esecutore')
                            ->relationship('executor', 'name', fn (Builder $query) => $query->where('is_person', true))
                            ->searchable()
                            ->preload(),
                        Select::make('executor_link')->label('Legame con l\'esecutore')->options(KycExecutorLink::class),
                        Select::make('executor_pep_status')->label('PEP esecutore')->options(KycPepStatus::class),
                    ]),
                Section::make('Titolari effettivi')
                    ->visible(fn (Get $get): bool => ! $this->getOwnerRecord()->is_person || (bool) $get('acts_for_third_party'))
                    ->schema([
                        Actions::make([
                            Action::make('prefill_owners')
                                ->label('Precompila da cariche sociali')
                                ->action(function (Set $set): void {
                                    $client = $this->getOwnerRecord();

                                    $set('beneficialOwners', app(BeneficialOwnerSuggester::class)
                                        ->suggest($client->companyRelations, $client->legal_representative_id));
                                }),
                        ]),
                        Repeater::make('beneficialOwners')
                            ->label('Titolari effettivi')
                            ->relationship()
                            ->maxItems(3)
                            ->orderColumn('position')
                            ->columns(2)
                            ->schema([
                                Select::make('client_id')
                                    ->label('Persona')
                                    ->relationship('person', 'name', fn (Builder $query) => $query->where('is_person', true))
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                TextInput::make('shares_percentage')
                                    ->label('Quote (%)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100),
                                Select::make('control_criterion')->label('Criterio di controllo')->options(KycControlCriterion::class),
                                Select::make('pep_status')->label('Condizione PEP')->options(KycPepStatus::class),
                                DatePicker::make('declaration_signed_at')->label('Dichiarazione firmata il'),
                                Toggle::make('is_verified')->label('Verificato'),
                            ]),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('document')->orderByDesc('compiled_at')->orderByDesc('id'))
            ->columns([
                TextColumn::make('compiled_at')->label('Compilato il')->dateTime('d/m/Y'),
                TextColumn::make('risk_level')
                    ->label('Rischio')
                    ->badge()
                    ->formatStateUsing(fn (?KycRiskLevel $state): ?string => $state?->getLabel())
                    ->color(fn (?KycRiskLevel $state): string => match ($state) {
                        KycRiskLevel::Low => 'success',
                        KycRiskLevel::Medium => 'warning',
                        KycRiskLevel::High => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->formatStateUsing(fn (?KycStatus $state): ?string => $state?->getLabel())
                    ->color(fn (?KycStatus $state): string => match ($state) {
                        KycStatus::Approved => 'success',
                        KycStatus::Complete => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('verified_by')->label('Verificato da'),
                TextColumn::make('verified_at')->label('Verificato il')->dateTime('d/m/Y'),
                TextColumn::make('document.expires_at')->label('Scade il')->date('d/m/Y'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Nuova compilazione')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['status'] = KycStatus::Draft->value;
                        $data['compiled_at'] ??= now();

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Modifica')
                    ->hidden(fn (KycQuestionnaire $record): bool => $record->status === KycStatus::Approved),
                Action::make('approve')
                    ->label('Approva')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (KycQuestionnaire $record): bool => $record->status !== KycStatus::Approved)
                    ->requiresConfirmation()
                    ->action(function (KycQuestionnaire $record): void {
                        try {
                            app(KycApprover::class)->approve($record, auth()->user());
                        } catch (\DomainException $e) {
                            Notification::make()->danger()->title('KYC incompleto')->body($e->getMessage())->send();

                            return;
                        } catch (PdfFormException $e) {
                            Notification::make()->danger()->title('Compilazione non disponibile')->body($e->getMessage())->send();

                            return;
                        }

                        $warnings = $record->warnings();

                        Notification::make()
                            ->success()
                            ->title('KYC approvato')
                            ->body($warnings === [] ? null : implode("\n", $warnings))
                            ->send();
                    }),
                Action::make('print')
                    ->label('Stampa QAV')
                    ->icon('heroicon-o-printer')
                    ->action(function (KycQuestionnaire $record) {
                        try {
                            $pdf = app(KycQavGenerator::class)->render($record);
                        } catch (\DomainException $e) {
                            Notification::make()->danger()->title('KYC incompleto')->body($e->getMessage())->send();

                            return null;
                        } catch (PdfFormException $e) {
                            Notification::make()->danger()->title('Compilazione non disponibile')->body($e->getMessage())->send();

                            return null;
                        }

                        return response()->streamDownload(fn () => print ($pdf), 'QAV.pdf');
                    }),
                SendForSignatureAction::make()
                    ->visible(fn (Action $action, KycQuestionnaire $record): bool => $record->status === KycStatus::Approved
                        && $record->document !== null
                        && \checkPiano('firma', $action->getLivewire()::class)
                        && SendForSignatureAction::isSignable($record->document)),
                DeleteAction::make()
                    ->label('Elimina')
                    ->hidden(fn (KycQuestionnaire $record): bool => $record->status === KycStatus::Approved),
            ]);
    }
}
