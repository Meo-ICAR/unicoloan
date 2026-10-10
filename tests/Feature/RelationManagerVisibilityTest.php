<?php

namespace Tests\Feature;

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
}
