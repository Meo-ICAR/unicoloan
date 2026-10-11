<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Unico\Core\Bridge\Jobs\RunBridgeCommand;

class BridgeCommandsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_commands_are_listed_and_queued_with_the_shared_key_only(): void
    {
        config(['unico-core.bridge.api_key' => 'chiave-prova']);
        Queue::fake();
        $key = ['X-Api-Key' => 'chiave-prova'];

        $this->getJson('/api/commands', $key)->assertOk()->assertJsonStructure(['commands' => ['documents:sync-schedule', 'documents:send-reminders', 'signature:sync']]);
        $run = $this->postJson('/api/commands/documents:send-reminders', ['options' => ['force' => true]], $key)->assertAccepted()->json('run_id');
        $this->postJson('/api/commands/documents:send-reminders', ['options' => ['altro' => 1]], $key)->assertStatus(422);
        $this->postJson('/api/commands/migrate:fresh', [], $key)->assertNotFound();
        $this->postJson('/api/commands/documents:sync-schedule')->assertUnauthorized();

        Queue::assertPushed(RunBridgeCommand::class, 1);
        $this->getJson("/api/commands/runs/{$run}", $key)->assertOk()->assertJsonPath('status', 'queued');
    }
}
