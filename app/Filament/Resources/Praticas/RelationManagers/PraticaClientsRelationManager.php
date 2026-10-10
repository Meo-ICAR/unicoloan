<?php

namespace App\Filament\Resources\Praticas\RelationManagers;

use App\Enums\PraticaClientRole;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Traits\HasRelationPlanAccess;
use App\Models\Client;
use App\Models\PraticaClient;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

/**
 * Richiedente, coobbligati e garanti della pratica: solo per i prodotti diversi da cessione/delega.
 */
class PraticaClientsRelationManager extends RelationManager
{
    use HasRelationPlanAccess {
        canViewForRecord as protected planCanViewForRecord;
    }

    protected static string $relationship = 'praticaClients';

    protected static ?string $title = 'Richiedente, coobbligati e garanti';

    protected static ?string $modelLabel = 'soggetto';

    protected static ?string $pluralModelLabel = 'soggetti';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return ! $ownerRecord->isCessioneDelega() && static::planCanViewForRecord($ownerRecord, $pageClass);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('client_id')
                ->label('Cliente')
                ->options(fn (): array => Client::query()->orderBy('name')->limit(200)->get()->mapWithKeys(fn (Client $client): array => [$client->getKey() => trim($client->name.' '.$client->first_name).' ('.$client->tax_code.')'])->all())
                ->getSearchResultsUsing(fn (string $search): array => Client::query()
                    ->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('first_name', 'like', "%{$search}%")->orWhere('tax_code', 'like', "%{$search}%"))
                    ->limit(50)->get()
                    ->mapWithKeys(fn (Client $client): array => [$client->getKey() => trim($client->name.' '.$client->first_name).' ('.$client->tax_code.')'])->all())
                ->getOptionLabelUsing(fn ($value): ?string => ($client = Client::find($value)) ? trim($client->name.' '.$client->first_name).' ('.$client->tax_code.')' : null)
                ->searchable()
                ->required(),
            Select::make('role')
                ->label('Ruolo')
                ->options(PraticaClientRole::class)
                ->required()
                ->unique(
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule, callable $get): Unique => $rule
                        ->where('pratica_id', $this->getOwnerRecord()->getKey())
                        ->where('client_id', $get('client_id')),
                )
                ->validationMessages(['unique' => 'Questo cliente ha già questo ruolo nella pratica.']),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn ($query) => $query->with('client'))
            ->defaultSort('role')
            ->columns([
                TextColumn::make('role')->label('Ruolo')->badge()->sortable(),
                TextColumn::make('client.name')
                    ->label('Cliente')
                    ->state(fn (PraticaClient $record): string => trim($record->client?->name.' '.$record->client?->first_name))
                    ->url(fn (PraticaClient $record): ?string => $record->client_id ? ClientResource::getUrl('edit', ['record' => $record->client_id]) : null),
                TextColumn::make('client.tax_code')->label('Cod. fiscale / P.IVA'),
            ])
            ->headerActions([
                CreateAction::make()->label('Aggiungi soggetto'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
