<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Throwable;

/**
 * Ogni risorsa del pannello admin deve aprire la pagina elenco senza errori sulle tabelle del pacchetto.
 */
class AdminResourcesSmokeTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_every_admin_resource_list_page_opens(): void
    {
        $this->seed();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->withoutExceptionHandling();

        $failures = [];
        $opened = 0;

        foreach (Filament::getPanel('admin')->getResources() as $resource) {
            if (! array_key_exists('index', $resource::getPages())) {
                continue;
            }

            try {
                $status = $this->get($resource::getUrl('index', panel: 'admin'))->getStatusCode();
                if ($status === 200) {
                    $opened++;
                } else {
                    $failures[$resource] = "HTTP {$status}";
                }
            } catch (UrlGenerationException) {
                // risorsa annidata: la sua pagina elenco richiede il record padre nell'URL
            } catch (Throwable $e) {
                $failures[$resource] = class_basename($e).': '.mb_substr($e->getMessage(), 0, 160);
            }
        }

        $this->assertSame([], $failures, print_r($failures, true));
        $this->assertGreaterThan(10, $opened);
    }
}
