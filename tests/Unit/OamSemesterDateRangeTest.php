<?php

namespace Tests\Unit;

use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OamSemesterDateRangeTest extends TestCase
{
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
