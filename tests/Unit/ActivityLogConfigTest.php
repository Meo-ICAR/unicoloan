<?php

namespace Tests\Unit;

use Tests\TestCase;

class ActivityLogConfigTest extends TestCase
{
    public function test_activity_retention_default_is_ten_years(): void
    {
        $this->assertGreaterThanOrEqual(3650, (int) config('activitylog.clean_after_days'));
    }
}
