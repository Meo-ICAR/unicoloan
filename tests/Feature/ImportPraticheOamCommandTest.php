<?php

namespace Tests\Feature;

use App\Services\ImportPraticheService;
use App\ValueObjects\OamSemester;
use Mockery;
use Tests\TestCase;

class ImportPraticheOamCommandTest extends TestCase
{
    public function test_command_uses_explicit_year_and_semester_options(): void
    {
        $spy = Mockery::mock(ImportPraticheService::class);
        $spy->shouldReceive('import')
            ->once()
            ->with(Mockery::on(function (OamSemester $semester): bool {
                return $semester->year === 2024 && $semester->semesterNumber === 2;
            }))
            ->andReturn(7);

        $this->app->instance(ImportPraticheService::class, $spy);

        $this->artisan('oam:import-pratiche', ['--year' => '2024', '--semester' => '2'])
            ->expectsOutputToContain('202412')
            ->expectsOutputToContain('7 pratiche')
            ->assertExitCode(0);
    }

    public function test_command_returns_failure_exit_code_on_exception(): void
    {
        $spy = Mockery::mock(ImportPraticheService::class);
        $spy->shouldReceive('import')->once()->andThrow(new \RuntimeException('boom'));

        $this->app->instance(ImportPraticheService::class, $spy);

        $this->artisan('oam:import-pratiche')
            ->expectsOutputToContain('boom')
            ->assertExitCode(1);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
