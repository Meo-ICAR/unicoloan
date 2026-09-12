<?php

namespace App\Filament\Unicofin\Resources\Clients\RelationManagers;

use App\Models\ClientMandate;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Forms;
use Filament\Tables;
use Illuminate\Database\Eloquent\Model;

class ClientMandatesRelationManager extends RelationManager
{
    protected static string $relationship = 'clientMandates';

    protected static ?string $title = 'Mandati Cliente';

    protected static ?string $modelLabel = 'Mandato Cliente';

    protected static ?string $pluralModelLabel = 'Mandati Cliente';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('numero_mandato')
                    ->label('Numero Mandato')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->default(function () {
                        // Genera automaticamente: MAND-PROGRESSIVO-ANNO
                        $year = date('Y');

                        // Trova l'ultimo progressivo per questo anno
                        $lastProgressive = ClientMandate::whereYear('created_at', '=', $year)
                            ->orderBy('numero_mandato', 'desc')
                            ->first();

                        if ($lastProgressive) {
                            // Estrai il numero progressivo (es: MAND-000001-2026 -> 000001)
                            preg_match('/MAND-(\d{6})-\d{4}/', $lastProgressive->numero_mandato, $matches);
                            $progressive = ($matches[1] ?? '000001') + 1;
                        } else {
                            $progressive = 1;
                        }

                        return 'MAND-' . str_pad($progressive, 6, '0', STR_PAD_LEFT) . "-{$year}";
                    }),
                TextInput::make('importo_richiesto_mandato')
                    ->label('Importo Richiesto')
                    ->numeric()
                    ->prefix('€')
                    ->step(100),
                TextInput::make('scopo_finanziamento')
                    ->label('Scopo Finanziamento')
                    ->maxLength(255),
                Select::make('stato')
                    ->label('Stato')
                    ->options([
                        'attivo' => 'Attivo',
                        'concluso_con_successo' => 'Concluso con Successo',
                        'scaduto' => 'Scaduto',
                        'revocato' => 'Revocato',
                    ])
                    ->default('attivo')
                    ->required(),
                Grid::make(3)->schema([
                    DatePicker::make('data_firma_mandato')
                        ->label('Data Firma Mandato')
                        ->default(now())
                        ->required()
                        ->native(false),
                    DatePicker::make('data_scadenza_mandato')
                        ->label('Data Scadenza Mandato')
                        ->default(now()->addYears(2))
                        ->native(false),
                    DatePicker::make('data_consegna_trasparenza')
                        ->default(now())
                        ->label('Data Consegna Trasparenza')
                        ->native(false),
                ])->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('numero_mandato')
            ->columns([
                TextColumn::make('numero_mandato')
                    ->label('Numero Mandato')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('data_firma_mandato')
                    ->label('Data Firma')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('data_scadenza_mandato')
                    ->label('Data Scadenza')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('importo_richiesto_mandato')
                    ->label('Importo Richiesto')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('scopo_finanziamento')
                    ->label('Scopo Finanziamento')
                    ->searchable()
                    ->limit(50),
                TextColumn::make('stato')
                    ->label('Stato')
                    ->badge()
                    ->colors([
                        'attivo' => 'success',
                        'concluso_con_successo' => 'primary',
                        'scaduto' => 'warning',
                        'revocato' => 'danger',
                    ]),
                TextColumn::make('created_at')
                    ->label('Creato il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                /*
                 * SelectFilter::make('stato')
                 *     ->label('Stato')
                 *     ->options([
                 *         'attivo' => 'Attivo',
                 *         'concluso_con_successo' => 'Concluso con Successo',
                 *         'scaduto' => 'Scaduto',
                 *         'revocato' => 'Revocato',
                 *     ]),
                 * Filter::make('data_firma_mandato')
                 *     ->label('Data Firma')
                 *     ->form([
                 *         DatePicker::make('from')
                 *             ->label('Dal')
                 *             ->native(false),
                 *         DatePicker::make('until')
                 *             ->label('Al')
                 *             ->native(false),
                 *     ])
                 *     ->query(function (Filter $query, array $data): Filter {
                 *         return $query
                 *             ->when(
                 *                 $data['from'],
                 *                 fn(Filter $query, $date): Filter => $query->whereDate('data_firma_mandato', '>=', $date),
                 *             )
                 *             ->when(
                 *                 $data['until'],
                 *                 fn(Filter $query, $date): Filter => $query->whereDate('data_firma_mandato', '<=', $date),
                 *             );
                 *     }),
                 */
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
               
                
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                CreateAction::make(),
            ]);
    }
}
