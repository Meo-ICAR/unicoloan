<?php

namespace Tests\Feature;

use App\Filament\Resources\Clientis\ClientiResource;
use App\Filament\Resources\Fornitores\FornitoreResource;
use App\Filament\Resources\Praticas\PraticaResource;
use App\Filament\Resources\Tipoprodottos\TipoprodottoResource;
use Tests\TestCase;

class FilamentResourceDiscoveryTest extends TestCase
{
    /**
     * These resources previously declared `namespace App\Filament\Unicofin\Resources\...`
     * while physically living under app/Filament/Resources, which is scanned by the
     * `admin` panel under the `App\Filament\Resources` namespace. Because Filament's
     * discovery derives the FQCN from the file path, the mismatched namespace meant
     * the expected class was never defined and the resource was invisible in every panel.
     */
    public function test_domain_resources_are_registered_in_the_admin_panel(): void
    {
        $panel = filament()->getPanel('admin');

        $registered = $panel->getResources();

        foreach ([
            ClientiResource::class,
            FornitoreResource::class,
            PraticaResource::class,
            TipoprodottoResource::class,
        ] as $resourceClass) {
            $this->assertContains($resourceClass, $registered, "$resourceClass is not registered in the admin panel.");
        }
    }
}
