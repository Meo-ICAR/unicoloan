<?php

namespace App\Filament\Resources\Praticas\RelationManagers;

use App\Filament\Resources\PraticaRequisitoOperativos\PraticaRequisitoOperativoResource;
use App\Filament\Traits\HasRelationPlanAccess;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RequisitiOperativiRelationManager extends RelationManager
{
    use HasRelationPlanAccess {
        canViewForRecord as protected planCanViewForRecord;
    }

    protected static string $relationship = 'requisitiOperativi';

    protected static ?string $title = 'Requisiti Operativi';

    protected static ?string $relatedResource = PraticaRequisitoOperativoResource::class;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if ($ownerRecord->is_notowned) {
            return false;
        }

        return static::planCanViewForRecord($ownerRecord, $pageClass);
    }

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
