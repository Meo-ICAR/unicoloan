<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Traits\HasRelationPlanAccess;
use App\Models\Client;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table; // <-- Importa il trait
use Illuminate\Database\Eloquent\Model;

class WebsitesRelationManager extends RelationManager
{
    use HasRelationPlanAccess {  // <-- Basta questo! Controlla automaticamente checkPiano('websites')
        canViewForRecord as protected planCanViewForRecord;
    }

    protected static string $relationship = 'websites';

    /**
     * Per i clienti (App\Models\Client) i siti web hanno senso solo per le
     * società: se il record è una persona fisica (is_person = true), nascondi
     * la scheda a prescindere dal piano/permesso.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if ($ownerRecord instanceof Client && $ownerRecord->is_person) {
            return false;
        }

        return static::planCanViewForRecord($ownerRecord, $pageClass);
    }

    protected static ?string $title = 'Siti web';

    protected static ?string $modelLabel = 'Sito web';

    protected static ?string $pluralModelLabel = 'Siti web';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('domain')
                    ->label('Dominio')
                    ->url(fn ($record) => $record?->domain ? (str_starts_with($record->domain, 'http') ? $record->domain : "https://{$record->domain}") : null)
                    ->required(),
                ToggleColumn::make('is_active')
                    ->default(true)
                    //   ->boolean()
                    //   ->trueIcon('heroicon-o-check-circle')
                    //   ->falseIcon('heroicon-o-x-circle')
                    ->label('Attivo'),
                Select::make('type')
                    ->label('Tipologia')
                    ->live()
                    ->placeholder('es. social per FB / Istagram, landing mandataria')
                    ->options([
                        'istituzionale' => 'Istituzionale',
                        'social' => 'Social',
                        'landing' => 'Landing',
                        'vetrina' => 'Vetrina',
                        'e-commerce' => 'E-commerce',
                        'altro' => 'Altro',
                    ]),
                TextInput::make('url_transparency')
                    ->label('URL trasparenza')
                    ->visible(fn ($get) => $get('type') === 'istituzionale')
                    ->url(fn ($record) => $record?->url_transparency ? (str_starts_with($record->url_transparency, 'http') ? $record->url_transparency : "https://{$record->url_transparency}") : null),
                DatePicker::make('transparency_date')
                    ->label('Data trasparenza')
                    ->visible(fn ($get) => $get('type') === 'istituzionale')
                    ->native(false)
                    ->displayFormat('d/m/y'),
                TextInput::make('name')
                    ->default(fn ($get) => $get('type'))
                    ->label('Nome sito')
                    ->required(),
                Toggle::make('is_active')
                    ->label('Attivo')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('domain')
            ->columns([
                TextColumn::make('domain')
                    ->label('Dominio')
                    ->openUrlInNewTab()
                    ->url(fn ($record) => str_starts_with($record->domain, 'http') ? $record->domain : "https://{$record->domain}")
                    ->openUrlInNewTab()
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Tipologia')
                    ->sortable()
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('Attivo')
                    ->sortable()
                    ->boolean(),
                TextColumn::make('url_transparency')
                    ->visible(fn ($record) => $record?->type === 'istituzionale')
                    ->label('URL trasparenza')
                    ->openUrlInNewTab()
                    ->url(fn ($record) => $record->url_transparency ? (str_starts_with($record->url_transparency, 'http') ? $record->url_transparency : "https://{$record->url_transparency}") : null),
                TextColumn::make('transparency_date')
                    ->visible(fn ($record) => $record?->type === 'istituzionale')
                    ->label('Trasparenza')
                    ->date('d/m/y')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),

                /*
                 * TextColumn::make('client.name')
                 *  ->label('Mandante')
                 *  ->searchable(),
                 * TextColumn::make('privacy_date')
                 *     ->label('Privacy')
                 *     ->date('d/m/y')
                 *     ->sortable(),
                 * TextColumn::make('privacy_prior_date')
                 *     ->label('Privacy precedente')
                 *     ->date('d/m/y')
                 *     ->sortable()
                 *     ->toggleable(isToggledHiddenByDefault: true),
                 * TextColumn::make('transparency_prior_date')
                 *     ->label('Trasparenza precedente')
                 *     ->date('d/m/y')
                 *     ->sortable()
                 *     ->toggleable(isToggledHiddenByDefault: true),
                 * TextColumn::make('url_privacy')
                 *     ->label('URL privacy')
                 *     ->searchable()
                 *     ->toggleable(isToggledHiddenByDefault: true),
                 * TextColumn::make('url_cookies')
                 *     ->label('URL cookie')
                 *     ->searchable()
                 *     ->toggleable(isToggledHiddenByDefault: true),
                 * IconColumn::make('is_footercompilant')
                 *     ->label('Footer conforme')
                 *     ->boolean()
                 *     ->toggleable(isToggledHiddenByDefault: true),
                 *     IconColumn::make('is_iso27001_certified')
                 *  ->label('ISO 27001')
                 *  ->boolean()
                 *  ->toggleable(isToggledHiddenByDefault: true),
                 */
            ])
            ->filters([
                TrashedFilter::make()
                    ->label('Eliminati'),
            ])
            ->headerActions([
                CreateAction::make(),
                //  AssociateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                //   DissociateAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //     DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
