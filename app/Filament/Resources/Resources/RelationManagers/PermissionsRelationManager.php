<?php

namespace App\Filament\Resources\Resources\RelationManagers;

use App\Models\EmployeeType;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class PermissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'permissions';

    protected static ?string $title = 'Matrice Permessi e Accessi';

    public function table(Table $table): Table
    {
        return $table
            // Elenco dei tipi di dipendente / ruoli censiti
            ->query(EmployeeType::query())
            ->columns([
                TextColumn::make('name')
                    ->label('Tipo Dipendente / Ruolo')
                    ->searchable()
                    ->sortable(),

                /* =========================================================================
                 | TOGGLE MASTER (Abilita / Disabilita tutto per questo ruolo)
                 | ========================================================================= */
                ToggleColumn::make('all_permissions')
                    ->label('Tutti')
                    ->getStateUsing(function (EmployeeType $record): bool {
                        $actions = ['viewAny', 'view', 'create', 'update', 'delete'];
                        $count = $this->getOwnerRecord()->permissions()
                            ->where('employee_type_id', $record->id)
                            ->whereIn('action', $actions)
                            ->count();

                        return $count === count($actions);
                    })
                    ->updateStateUsing(function (EmployeeType $record, bool $state): void {
                        $actions = ['viewAny', 'view', 'create', 'update', 'delete'];
                        $owner = $this->getOwnerRecord();

                        if ($state) {
                            foreach ($actions as $action) {
                                $owner->permissions()->firstOrCreate([
                                    'employee_type_id' => $record->id,
                                    'action' => $action,
                                ]);
                            }
                        } else {
                            $owner->permissions()
                                ->where('employee_type_id', $record->id)
                                ->whereIn('action', $actions)
                                ->delete();
                        }
                    }),

                /* =========================================================================
                 | TOGGLE SINGOLE AZIONI CRUD
                 | ========================================================================= */
                ToggleColumn::make('perm_view_any')
                    ->label('Elenco (viewAny)')
                    ->getStateUsing(fn (EmployeeType $record) => $this->hasPermission($record->id, 'viewAny'))
                    ->updateStateUsing(fn (EmployeeType $record, bool $state) => $this->togglePermission($record->id, 'viewAny', $state)),

                ToggleColumn::make('perm_view')
                    ->label('Dettaglio (view)')
                    ->getStateUsing(fn (EmployeeType $record) => $this->hasPermission($record->id, 'view'))
                    ->updateStateUsing(fn (EmployeeType $record, bool $state) => $this->togglePermission($record->id, 'view', $state)),

                ToggleColumn::make('perm_create')
                    ->label('Crea (create)')
                    ->getStateUsing(fn (EmployeeType $record) => $this->hasPermission($record->id, 'create'))
                    ->updateStateUsing(fn (EmployeeType $record, bool $state) => $this->togglePermission($record->id, 'create', $state)),

                ToggleColumn::make('perm_update')
                    ->label('Modifica (update)')
                    ->getStateUsing(fn (EmployeeType $record) => $this->hasPermission($record->id, 'update'))
                    ->updateStateUsing(fn (EmployeeType $record, bool $state) => $this->togglePermission($record->id, 'update', $state)),

                ToggleColumn::make('perm_delete')
                    ->label('Elimina (delete)')
                    ->getStateUsing(fn (EmployeeType $record) => $this->hasPermission($record->id, 'delete'))
                    ->updateStateUsing(fn (EmployeeType $record, bool $state) => $this->togglePermission($record->id, 'delete', $state)),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Azione Rapida: Concedi TUTTE le azioni per TUTTI i tipi di dipendente su questa risorsa
                Action::make('grantAllGlobal')
                    ->label('Abilita Tutto')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function () {
                        $employeeTypes = EmployeeType::all();
                        $actions = ['viewAny', 'view', 'create', 'update', 'delete'];
                        $owner = $this->getOwnerRecord();

                        foreach ($employeeTypes as $type) {
                            foreach ($actions as $action) {
                                $owner->permissions()->firstOrCreate([
                                    'employee_type_id' => $type->id,
                                    'action' => $action,
                                ]);
                            }
                        }
                    }),

                // Azione Rapida: Revoca TUTTI i permessi su questa risorsa
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

    protected function hasPermission(int $employeeTypeId, string $action): bool
    {
        return $this->getOwnerRecord()->permissions()
            ->where('employee_type_id', $employeeTypeId)
            ->where('action', $action)
            ->exists();
    }

    protected function togglePermission(int $employeeTypeId, string $action, bool $state): void
    {
        $owner = $this->getOwnerRecord();

        if ($state) {
            $owner->permissions()->firstOrCreate([
                'employee_type_id' => $employeeTypeId,
                'action' => $action,
            ]);
        } else {
            $owner->permissions()
                ->where('employee_type_id', $employeeTypeId)
                ->where('action', $action)
                ->delete();
        }
    }
}
