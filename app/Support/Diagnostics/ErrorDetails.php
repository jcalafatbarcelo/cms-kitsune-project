<?php

namespace App\Support\Diagnostics;

class ErrorDetails
{
    public static function enabled(): bool
    {
        return (bool) config('diagnostics.error_details')
            && ! self::isProduction();
    }

    public static function isProduction(): bool
    {
        return strtolower((string) app()->environment()) === 'production';
    }
}
