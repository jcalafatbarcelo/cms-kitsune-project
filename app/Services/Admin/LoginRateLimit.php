<?php

namespace App\Services\Admin;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use LogicException;

class LoginRateLimit
{
    public const VERIFICATION_LOCK = 'admin-login:verify-rate-limit-lock';

    private const MINIMUM_ADMISSION_LOCK_SECONDS = 60;

    private const LOCK_RELEASE_GRACE_SECONDS = 5;

    public function attempt(string $email, string $ip, Closure $authenticate): int
    {
        // Match the identity used by the auth provider, including database collation aliases.
        $identity = mb_check_encoding($email, 'UTF-8') && strlen($email) <= 1020
            ? User::query()->where('email', $email)->value('email') ?? $email
            : $email;
        $key = 'admin-login:'.hash('sha256', AdminCredentials::email($identity)).'|'.$ip;

        return Cache::lock($key.':admission', $this->admissionLockSeconds())->block(5, function () use ($key, $authenticate): int {
            if (RateLimiter::tooManyAttempts($key, 5)) {
                return max(1, RateLimiter::availableIn($key));
            }
            if (! $authenticate()) {
                RateLimiter::hit($key, 60);
            }

            return 0;
        });
    }

    private function admissionLockSeconds(): int
    {
        $maximumRequestSeconds = (int) ini_get('max_execution_time');
        if (app()->environment('production') && $maximumRequestSeconds <= 0) {
            throw new LogicException('Production login requests require a finite max_execution_time.');
        }

        return max(
            self::MINIMUM_ADMISSION_LOCK_SECONDS,
            $maximumRequestSeconds > 0 ? $maximumRequestSeconds + self::LOCK_RELEASE_GRACE_SECONDS : 0,
        );
    }
}
