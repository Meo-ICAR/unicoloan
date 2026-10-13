<?php

namespace App\Support\Agenti;

final class AgentiDevBypass
{
    public static function isEnabled(): bool
    {
        return app()->environment('local')
            && (bool) config('agenti.dev_bypass.enabled', false);
    }

    public static function abortUnlessEnabled(): void
    {
        if (! self::isEnabled()) {
            abort(404);
        }
    }
}
