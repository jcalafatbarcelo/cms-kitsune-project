<?php

namespace App\Services\Admin;

use Closure;
use Illuminate\Validation\Rules\Password;

class AdminCredentials
{
    public static function email(string $email): string
    {
        return mb_strtolower(trim($email), 'UTF-8');
    }

    public static function passwordRules(): array
    {
        return ['required', 'string', Password::min(15)->mixedCase()->numbers()->symbols(), function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || ! mb_check_encoding($value, 'UTF-8') || strlen($value) > 72 || str_contains($value, "\0")) {
                $fail('The password must be valid UTF-8, contain no null bytes, and not exceed 72 bytes.');
            }
        }];
    }
}
