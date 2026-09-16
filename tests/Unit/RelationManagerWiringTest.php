<?php

namespace Tests\Unit;

use App\Filament\Resources\Clientis\RelationManagers\BlacklistRelationManager as ClientiBlacklistRelationManager;
use App\Filament\Resources\Employees\RelationManagers\BlacklistRelationManager as EmployeeBlacklistRelationManager;
use App\Filament\Resources\Fornitores\RelationManagers\BlacklistRelationManager as FornitoreBlacklistRelationManager;
use App\Filament\Resources\Praticas\RelationManagers\RequisitiOperativiRelationManager;
use App\Filament\Resources\Praticas\RelationManagers\StatusHistoryRelationManager;
use App\Models\BlacklistClienteEmployee;
use App\Models\BlacklistClienteFornitore;
use App\Models\Employee;
use App\Models\PraticaRequisitoOperativo;
use App\Models\PraticaStatusHistory;
use App\Models\PROFORMA\Clienti;
use App\Models\PROFORMA\Fornitore;
use App\Models\PROFORMA\Pratica;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

/**
 * Guards the wiring between each RelationManager and the Eloquent relation it
 * points at: the exact class of bug (wrong relationship name, missing model
 * method, or a related model silently inheriting the wrong database
 * connection through the relation) that broke every one of these relation
 * managers at least once while they were being built.
 */
class RelationManagerWiringTest extends TestCase
{
    /**
     * @return array<string, array{0: class-string, 1: class-string<Model>, 2: class-string<Model>, 3: string}>
     */
    public static function relationManagerProvider(): array
    {
        return [
            'Clienti blacklist' => [ClientiBlacklistRelationManager::class, Clienti::class, BlacklistClienteFornitore::class, 'mysql_proforma'],
            'Fornitore blacklist' => [FornitoreBlacklistRelationManager::class, Fornitore::class, BlacklistClienteFornitore::class, 'mysql_proforma'],
            'Employee blacklist' => [EmployeeBlacklistRelationManager::class, Employee::class, BlacklistClienteEmployee::class, 'mysql_proforma'],
            'Pratica requisiti operativi' => [RequisitiOperativiRelationManager::class, Pratica::class, PraticaRequisitoOperativo::class, 'mysql'],
            'Pratica status history' => [StatusHistoryRelationManager::class, Pratica::class, PraticaStatusHistory::class, 'mysql_proforma'],
        ];
    }

    /**
     * @param  class-string  $relationManagerClass
     * @param  class-string<Model>  $ownerModelClass
     * @param  class-string<Model>  $expectedRelatedClass
     */
    #[DataProvider('relationManagerProvider')]
    public function test_relation_manager_relationship_resolves_to_the_expected_model(
        string $relationManagerClass,
        string $ownerModelClass,
        string $expectedRelatedClass,
        string $expectedConnection,
    ): void {
        $relationshipName = (new ReflectionClass($relationManagerClass))
            ->getProperty('relationship')
            ->getDefaultValue();

        $owner = new $ownerModelClass;

        $this->assertTrue(
            method_exists($owner, $relationshipName),
            "{$ownerModelClass} has no method '{$relationshipName}' declared by {$relationManagerClass}::\$relationship."
        );

        $relation = $owner->{$relationshipName}();

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertInstanceOf($expectedRelatedClass, $relation->getRelated());

        $this->assertSame(
            $expectedConnection,
            $relation->getRelated()->getConnectionName(),
            "{$expectedRelatedClass} resolved on the wrong connection when loaded via {$ownerModelClass}::{$relationshipName}() ".
            '(likely inheriting the owner\'s connection instead of declaring its own explicitly).'
        );
    }
}
