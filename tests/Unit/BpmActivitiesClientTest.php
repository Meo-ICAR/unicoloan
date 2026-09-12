<?php

namespace Tests\Unit;

use App\Services\BpmActivitiesClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BpmActivitiesClientTest extends TestCase
{
    public function test_posts_model_fields_to_bpm_and_returns_the_activities(): void
    {
        config()->set('services.bpm.url', 'https://bpm.test');

        Http::fake([
            'https://bpm.test/api/bpm/available-activities' => Http::response([
                'activities' => [
                    ['code' => 'PRC-AML', 'name' => 'Antiriciclaggio', 'description' => null],
                ],
            ]),
        ]);

        $activities = app(BpmActivitiesClient::class)->availableFor('fornitore', 'abc-123', ['stipulated_at' => null]);

        $this->assertSame([
            ['code' => 'PRC-AML', 'name' => 'Antiriciclaggio', 'description' => null],
        ], $activities);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://bpm.test/api/bpm/available-activities'
                && $request['model_type'] === 'fornitore'
                && $request['model_id'] === 'abc-123'
                && $request['fields'] === ['stipulated_at' => null];
        });
    }

    public function test_returns_an_empty_list_when_the_bpm_call_fails(): void
    {
        config()->set('services.bpm.url', 'https://bpm.test');

        Http::fake([
            'https://bpm.test/api/bpm/available-activities' => Http::response(null, 500),
        ]);

        $activities = app(BpmActivitiesClient::class)->availableFor('fornitore', 'abc-123', []);

        $this->assertSame([], $activities);
    }
}
