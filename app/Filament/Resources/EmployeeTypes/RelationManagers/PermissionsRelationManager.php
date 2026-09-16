<?php

namespace App\Filament\Resources\EmployeeTypes\RelationManagers;

use App\Filament\Traits\HasRelationPlanAccess;
use App\Models\Resource;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PermissionsRelationManager extends RelationManager
{
    use HasRelationPlanAccess;

    protected static string $relationship = 'permissions';

    protected static ?string $title = 'Matrice Permessi e Accessi';

    public function table(Table $table): Table
    {
        return $table
            // Mostriamo la lista completa delle risorse censite nel sistema
            ->query(Resource::query())
            ->columns([
                TextColumn::make('name')
                    ->label('Risorsa')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Resource $record) => "Chiave: {$record->key}"),

                TextColumn::make('group')
                    ->label('Gruppo')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                /* =========================================================================
                 | TOGGLE MASTER (Abilita / Disabilita tutto per questa risorsa)
                 | ========================================================================= */
                ToggleColumn::make('all_permissions')
                    ->label('Tutti')
                    ->getStateUsing(function (Resource $record): bool {
                        $actions = ['viewAny', 'view', 'create', 'update', 'delete'];
                        $count = $this->getOwnerRecord()->permissions()
                            ->where('resource', $record->key)
                            ->whereIn('action', $actions)
                            ->count();

                        return $count === count($actions);
                    })
                    ->updateStateUsing(function (Resource $record, bool $state): void {
                        $actions = ['viewAny', 'view', 'create', 'update', 'delete'];
                        $owner = $this->getOwnerRecord();

                        if ($state) {
                            foreach ($actions as $action) {
                                $owner->permissions()->firstOrCreate([
                                    'resource' => $record->key,
                                    'action' => $action,
                                ]);
                            }
                        } else {
                            $owner->permissions()
                                ->where('resource', $record->key)
                                ->whereIn('action', $actions)
                                ->delete();
                        }
                    }),

                /* =========================================================================
                 | TOGGLE SINGOLE AZIONI CRUD
                 | ========================================================================= */
                ToggleColumn::make('perm_view_any')
                    ->label('Elenco (viewAny)')
                    ->getStateUsing(fn (Resource $record) => $this->hasPermission($record->key, 'viewAny'))
                    ->updateStateUsing(fn (Resource $record, bool $state) => $this->togglePermission($record->key, 'viewAny', $state)),

                ToggleColumn::make('perm_view')
                    ->label('Dettaglio (view)')
                    ->getStateUsing(fn (Resource $record) => $this->hasPermission($record->key, 'view'))
                    ->updateStateUsing(fn (Resource $record, bool $state) => $this->togglePermission($record->key, 'view', $state)),

                ToggleColumn::make('perm_create')
                    ->label('Crea (create)')
                    ->getStateUsing(fn (Resource $record) => $this->hasPermission($record->key, 'create'))
                    ->updateStateUsing(fn (Resource $record, bool $state) => $this->togglePermission($record->key, 'create', $state)),

                ToggleColumn::make('perm_update')
                    ->label('Modifica (update)')
                    ->getStateUsing(fn (Resource $record) => $this->hasPermission($record->key, 'update'))
                    ->updateStateUsing(fn (Resource $record, bool $state) => $this->togglePermission($record->key, 'update', $state)),

                ToggleColumn::make('perm_delete')
                    ->label('Elimina (delete)')
                    ->getStateUsing(fn (Resource $record) => $this->hasPermission($record->key, 'delete'))
                    ->updateStateUsing(fn (Resource $record, bool $state) => $this->togglePermission($record->key, 'delete', $state)),
            ])
            ->filters([
                SelectFilter::make('group')
                    ->label('Filtra per Gruppo')
                    ->options(fn () => Resource::query()->whereNotNull('group')->pluck('group', 'group')->toArray()),
            ])
            ->headerActions([
                // Azione Rapida: Concedi TUTTO il sistema a questo Ruolo
                Action::make('grantAllGlobal')
                    ->label('Abilita Tutto')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function () {
                        $resources = Resource::all();
                        $actions = ['viewAny', 'view', 'create', 'update', 'delete'];
                        $owner = $this->getOwnerRecord();

                        foreach ($resources as $resource) {
                            foreach ($actions as $action) {
                                $owner->permissions()->firstOrCreate([
                                    'resource' => $resource->key,
                                    'action' => $action,
                                ]);
                            }
                        }
                    }),

                // Azione Rapida: Revoca TUTTI i permessi
                Action::make('removeAllGlobal')
                    ->label('Revoca Tutto')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn () => $this->getOwnerRecord()->permissions()->delete()),
            ]);
    }

    /* =========================================================================
     | HELPER METODI PRIVATI
     | ========================================================================= */

    protected function hasPermission(string $resourceKey, string $action): bool
    {
        return $this->getOwnerRecord()->permissions()
            ->where('resource', $resourceKey)
            ->where('action', $action)
            ->exists();
    }

    protected function togglePermission(string $resourceKey, string $action, bool $state): void
    {
        $owner = $this->getOwnerRecord();

        if ($state) {
            $owner->permissions()->firstOrCreate([
                'resource' => $resourceKey,
                'action' => $action,
            ]);
        } else {
            $owner->permissions()
                ->where('resource', $resourceKey)
                ->where('action', $action)
                ->delete();
        }
    }
}
