<?php

namespace App\Filament\Agenti\Resources\Pratiche\RelationManagers;

use App\Enums\KycStatus;
use Unico\Core\Enums\SignerRole;
use App\Filament\Actions\SendForSignatureAction;
use App\Filament\Agenti\Resources\Pratiche\Pages\ViewPraticaAgente;
use App\Filament\Resources\Clients\Schemas\KycQuestionnaireForm;
use App\Models\KycQuestionnaire;
use App\Models\PROFORMA\Pratica;
use App\Services\Agenti\ClientForPraticaCreator;
use App\Services\Kyc\KycQavGenerator;
use App\Services\PdfFormException;
use App\Services\Signature\Exceptions\SignatureRequestException;
use App\Services\Signature\SignatureRequestService;
use App\Services\Signature\SignerInput;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Adeguata verifica compilata dal produttore e sottoscritta dal cliente con firma OTP: il QAV si genera all'invio
 * e il questionario diventa approvato quando il cliente firma. Esecutore e titolari effettivi (persona giuridica o
 * terzi) restano all'istruttoria (pannello admin).
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
                    KycQuestionnaireForm::financingPurpose($isPerson, required: true),
                    KycQuestionnaireForm::riskLevel(required: true),
                    KycQuestionnaireForm::pepStatus($isPerson, required: true),
                    Textarea::make('notes')->label('Note')->columnSpanFull(),
                ]),
            KycQuestionnaireForm::personSection($isPerson, required: true, thirdPartyHelper: 'I titolari effettivi vengono inseriti dall\'istruttoria.'),
            Section::make('Persona giuridica')
                ->columns(2)
                ->visible(fn (): bool => ! $this->isPerson())
                ->schema(KycQuestionnaireForm::companyFields(required: true)),
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
                TextColumn::make('signature')
                    ->label('Firma del cliente')
                    ->state(fn (KycQuestionnaire $record): ?string => $record->document?->is_signed
                        ? 'Firmato'
                        : $record->document?->latestSignatureRequest()?->status->getLabel()),
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
                Action::make('sendForSignature')
                    ->label('Invia al cliente per firma OTP')
                    ->icon('heroicon-o-pencil-square')
                    ->color('success')
                    ->modalHeading('Invia al cliente per la firma OTP')
                    ->modalDescription('Il cliente riceve un\'email con il link e un SMS con il codice OTP per firmare l\'adeguata verifica.')
                    ->modalSubmitActionLabel('Invia')
                    ->visible(fn (KycQuestionnaire $record): bool => $this->isPerson()
                        && in_array($record->status, [KycStatus::Draft, KycStatus::Complete], true)
                        && ($record->document === null || SendForSignatureAction::isSignable($record->document)))
                    ->fillForm(fn (): array => $this->signerDefaults())
                    ->schema([
                        TextInput::make('first_name')->label('Nome')->required()->maxLength(100),
                        TextInput::make('last_name')->label('Cognome')->required()->maxLength(100),
                        TextInput::make('email')->label('Email')->email()->required(),
                        TextInput::make('phone')->label('Cellulare (OTP)')->tel()->required(),
                    ])
                    ->action(fn (array $data, KycQuestionnaire $record) => $this->sendForSignature($record, $data)),
                Action::make('refreshSignature')
                    ->label('Aggiorna stato firma')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (KycQuestionnaire $record): bool => $record->document?->latestSignatureRequest()?->isOpen() ?? false)
                    ->action(function (KycQuestionnaire $record): void {
                        $request = $record->document?->latestSignatureRequest();

                        if ($request === null) {
                            return;
                        }

                        try {
                            $request = app(SignatureRequestService::class)->reconcile($request);
                        } catch (SignatureRequestException $e) {
                            Notification::make()->danger()->title('Aggiornamento non riuscito')->body($e->getMessage())->send();

                            return;
                        }

                        Notification::make()->info()->title('Stato firma: '.$request->status->getLabel())->send();
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

        return $pratica->clienteAnagrafica() !== null
            && app(ClientForPraticaCreator::class)->isUsableByAgent((string) $pratica->codice_fiscale, $pratica->partita_iva_agente)
            && ! $pratica->kycQuestionnaires()->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function signerDefaults(): array
    {
        /** @var Pratica $pratica */
        $pratica = $this->getOwnerRecord();
        $client = $pratica->clienteAnagrafica();

        return [
            'first_name' => $client?->first_name,
            'last_name' => $client?->name,
            'email' => $client?->email,
            'phone' => $client?->phone,
        ];
    }

    /**
     * Genera il QAV (una sola volta) e lo invia in firma OTP al cliente. Se l'invio fallisce il QAV resta pronto per un nuovo tentativo.
     *
     * @param  array<string, mixed>  $data
     */
    private function sendForSignature(KycQuestionnaire $record, array $data): void
    {
        $missing = $record->fresh(['client', 'beneficialOwners'])->missingRequirements();

        if ($missing !== []) {
            Notification::make()->danger()->title('Compilazione incompleta')->body('Mancano: '.implode(', ', $missing).'.')->send();

            return;
        }

        $user = Auth::user();
        $record = $record->fresh(['client', 'beneficialOwners', 'document']);

        try {
            $document = $record->document ?? app(KycQavGenerator::class)->generate($record, $user);
        } catch (\DomainException|PdfFormException $e) {
            Notification::make()->danger()->title('QAV non disponibile')->body($e->getMessage())->send();

            return;
        }

        $record->update(['document_id' => $document->getKey(), 'status' => KycStatus::Complete, 'compiled_at' => now()]);

        $slot = collect($document->documentType?->pdfModule?->signature_slots ?? [])->firstWhere('role', SignerRole::Client->value);
        $client = $record->client;

        $signer = new SignerInput(
            (string) ($slot['slot'] ?? 'cliente'),
            SignerRole::Client,
            trim((string) $data['first_name']),
            trim((string) $data['last_name']),
            trim((string) $data['email']),
            trim((string) $data['phone']),
            $client?->tax_code,
            'manual',
            null,
        );

        try {
            app(SignatureRequestService::class)->send($document, [$signer], $user);
        } catch (SignatureRequestException $e) {
            Notification::make()->danger()->title('Invio per la firma non riuscito')->body($e->getMessage())->send();

            return;
        }

        Notification::make()->success()->title('Richiesta di firma inviata al cliente')->send();
    }
}
