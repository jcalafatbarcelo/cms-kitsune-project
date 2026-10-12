<?php

namespace App\Console\Commands;

use App\Services\Admin\LoginRateLimit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class VerifyLoginRateLimitLock extends Command
{
    protected $signature = 'admin:verify-login-rate-limit-lock {--hold=0 : Seconds to retain the verification lock, from 0 to 30}';

    protected $description = 'Verify that the configured cache store shares the admin login rate limit lock';

    public function handle(): int
    {
        $hold = (string) $this->option('hold');
        if (! ctype_digit($hold) || (int) $hold > 30) {
            $this->error('The --hold option must be an integer between 0 and 30.');

            return self::FAILURE;
        }

        $seconds = (int) $hold;
        $lock = Cache::lock(LoginRateLimit::VERIFICATION_LOCK, max(1, $seconds + 5));
        if (! $lock->get()) {
            $this->error('The login rate limit verification lock is already held.');

            return self::FAILURE;
        }

        try {
            if ($seconds > 0) {
                $this->info("Holding the login rate limit verification lock for {$seconds} seconds.");
                sleep($seconds);
            }
        } finally {
            $lock->release();
        }

        $this->info('The login rate limit verification lock was acquired and released.');

        return self::SUCCESS;
    }
}
