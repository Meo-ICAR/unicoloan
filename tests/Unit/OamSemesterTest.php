<?php

namespace Tests\Unit;

use App\ValueObjects\OamSemester;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OamSemesterTest extends TestCase
{
    public function test_first_semester_boundaries(): void
    {
        $s = new OamSemester(2026, 1);

        $this->assertSame('2026-01-01', $s->start->format('Y-m-d'));
        $this->assertSame('2026-06-30', $s->end->format('Y-m-d'));
        $this->assertSame('202606', $s->period());
    }

    public function test_second_semester_boundaries(): void
    {
        $s = new OamSemester(2026, 2);

        $this->assertSame('2026-07-01', $s->start->format('Y-m-d'));
        $this->assertSame('2026-12-31', $s->end->format('Y-m-d'));
        $this->assertSame('202612', $s->period());
    }

    public function test_invalid_semester_number_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OamSemester(2026, 3);
    }

    #[DataProvider('monthProvider')]
    public function test_current_maps_month_to_semester(int $month, int $expectedSemester, int $expectedYear): void
    {
        $now = CarbonImmutable::create(2026, $month, 15);

        $semester = OamSemester::current($now);

        $this->assertSame($expectedSemester, $semester->semesterNumber);
        $this->assertSame($expectedYear, $semester->year);
    }

    /**
     * @return array<string, array{int, int, int}>
     */
    public static function monthProvider(): array
    {
        return [
            'gennaio' => [1, 1, 2026],
            'giugno' => [6, 1, 2026],
            'ottobre' => [10, 1, 2026],
            'novembre' => [11, 2, 2026],
            'dicembre' => [12, 2, 2026],
        ];
    }

    #[DataProvider('periodProvider')]
    public function test_from_period_parses_year_and_semester(string $period, int $year, int $semester): void
    {
        $s = OamSemester::fromPeriod($period);

        $this->assertSame($year, $s->year);
        $this->assertSame($semester, $s->semesterNumber);
        $this->assertSame($period === '202501' ? '202506' : $period, $s->period());
    }

    /**
     * @return array<string, array{string, int, int}>
     */
    public static function periodProvider(): array
    {
        return [
            '202506' => ['202506', 2025, 1],
            '202501' => ['202501', 2025, 1],
            '202512' => ['202512', 2025, 2],
        ];
    }

    public function test_from_period_rejects_malformed_input(): void
    {
        $this->expectException(InvalidArgumentException::class);

        OamSemester::fromPeriod('2025-06');
    }

    public function test_legacy_alias_still_works(): void
    {
        $this->assertInstanceOf(OamSemester::class, OamSemester::getInBaseAlMeseCorrente());
    }
}
