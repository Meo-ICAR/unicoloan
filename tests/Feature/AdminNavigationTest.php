<?php

namespace Tests\Feature;

use App\Filament\Pages\AssistenteAi;
use App\Filament\Resources\ApiCalls\ApiCallResource;
use App\Filament\Resources\Clientis\ClientiResource;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\Fornitores\FornitoreResource;
use App\Filament\Resources\MailAccounts\MailAccountResource;
use App\Filament\Resources\Praticas\PraticaResource;
use App\Filament\Resources\Resources\ResourceResource;
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
        $this->assertEqualsCanonicalizing(
            ['Manuali', 'Settings'],
            collect(Filament::getNavigationGroups())->map->getLabel()->all()
        );
    }

    public function test_assistente_ai_is_in_the_manuali_group(): void
    {
        $this->assertSame('Manuali', AssistenteAi::getNavigationGroup());
    }

    public function test_selected_settings_resources_are_hidden_from_navigation(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->make(['role' => 'admin']));

        $hidden = [
            MailAccountResource::class,
            ClientiResource::class,
            ResourceResource::class,
            EmployeeResource::class,
            ApiCallResource::class,
        ];

        foreach ($hidden as $resource) {
            $this->assertFalse(
                $resource::shouldRegisterNavigation(),
                "{$resource} should not register navigation"
            );
        }
    }

    public function test_produttori_are_visible_in_settings_navigation(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->make(['role' => 'super_admin']));

        $this->assertTrue(FornitoreResource::shouldRegisterNavigation());
        $this->assertSame('Settings', FornitoreResource::getNavigationGroup());
        $this->assertSame('Produttori', FornitoreResource::getNavigationLabel());
    }
}
