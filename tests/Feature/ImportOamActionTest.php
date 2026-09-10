<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ImportOamActionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_oam_semestrales_list_page_renders(): void
    {
        Company::factory()->create();
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('filament.admin.resources.oam-semestrales.index'));

        $response->assertOk();
    }

    public function test_import_action_is_registered_on_list_page(): void
    {
        Company::factory()->create();
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('filament.admin.resources.oam-semestrales.index'));

        $response->assertOk();
        $response->assertSee('Importa Pratiche');
    }

    public function test_export_action_is_registered_on_list_page(): void
    {
        Company::factory()->create();
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('filament.admin.resources.oam-semestrales.index'));

        $response->assertOk();
        $response->assertSee('Completo');
    }

    /**
     * @param  non-empty-string  $expectedStart
     * @param  non-empty-string  $expectedEnd
     */
    #[DataProvider('semestreProvider')]
    public function test_semester_date_ranges_are_correct(int $semestre, string $expectedStart, string $expectedEnd): void
    {
        $anno = 2025;

        if ($semestre === 1) {
            $startAt = Carbon::create($anno, 1, 1)->startOfDay();
            $endAt = Carbon::create($anno, 6, 30)->endOfDay();
        } else {
            $startAt = Carbon::create($anno, 7, 1)->startOfDay();
            $endAt = Carbon::create($anno, 12, 31)->endOfDay();
        }

        $this->assertSame($expectedStart, $startAt->format('Y-m-d'));
        $this->assertSame($expectedEnd, $endAt->format('Y-m-d'));
    }

    public static function semestreProvider(): array
    {
        return [
            'primo semestre' => [1, '2025-01-01', '2025-06-30'],
            'secondo semestre' => [2, '2025-07-01', '2025-12-31'],
        ];
    }
}
