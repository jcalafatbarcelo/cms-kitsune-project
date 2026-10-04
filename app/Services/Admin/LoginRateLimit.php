<?php

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class LoginRateLimit
{
    public function retryAfter(string $email, string $ip): int
    {
        // Match the identity used by the auth provider, including database collation aliases.
        $identity = mb_check_encoding($email, 'UTF-8') && strlen($email) <= 1020
            ? User::query()->where('email', $email)->value('email') ?? $email
            : $email;
        $key = 'admin-login:'.hash('sha256', AdminCredentials::email($identity)).'|'.$ip;

        try {
            return Cache::lock($key.':admission', 10)->block(5, function () use ($key): int {
                if (RateLimiter::tooManyAttempts($key, 5)) {
                    return max(1, RateLimiter::availableIn($key));
                }
                RateLimiter::hit($key, 60);

                return 0;
            });
        } catch (LockTimeoutException) {
            return 60;
        }
    }
}
