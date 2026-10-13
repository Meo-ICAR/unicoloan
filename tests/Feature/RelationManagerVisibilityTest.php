<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\RelationManagers\ClientRelationsRelationManager;
use App\Filament\Resources\RelationManagers\DocumentsRelationManager;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelationManagerVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_documents_tab_is_visible_to_user_without_employee_profile(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']));

        $this->assertTrue(DocumentsRelationManager::canViewForRecord(new Client, 'EditClient'));
    }

    public function test_cariche_sociali_are_hidden_for_persons_and_is_company_suppliers(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']));

        $person = new Client(['is_person' => true, 'is_company' => false]);
        $supplier = new Client(['is_person' => false, 'is_company' => true]);
        $companyClient = new Client(['is_person' => false, 'is_company' => false]);

        $this->assertFalse(ClientRelationsRelationManager::canViewForRecord($person, EditClient::class));
        $this->assertFalse(ClientRelationsRelationManager::canViewForRecord($supplier, EditClient::class));
        $this->assertTrue(ClientRelationsRelationManager::canViewForRecord($companyClient, EditClient::class));
    }
}
