<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Praticas\PraticaResource;
use App\Models\User;
use Filament\Facades\Filament;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    public function test_only_clienti_and_pratiche_are_outside_the_settings_group(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->make(['role' => 'admin']));

        $outside = [];

        foreach (Filament::getResources() as $resource) {
            if ($resource::shouldRegisterNavigation() && $resource::getNavigationGroup() !== 'Settings') {
                $outside[] = $resource;
            }
        }

        $this->assertEqualsCanonicalizing([ClientResource::class, PraticaResource::class], $outside);
        $this->assertSame(['Settings'], collect(Filament::getNavigationGroups())->map->getLabel()->all());
    }
}
