<?php

use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Services\Admin\LoginRateLimit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

test('collation equivalent emails cannot obtain independent authentication budgets', function () {
    User::factory()->create(['email' => 'admin@example.test']);
    if (! User::query()->where('email', 'ádmin@example.test')->exists()) {
        $this->markTestSkipped('This database collation treats the two email identities as distinct.');
    }
    for ($i = 0; $i < 5; $i++) {
        $this->post('/admin/login', ['email' => 'admin@example.test', 'password' => 'Wrong-Test-Password9!'])->assertSessionHasErrors('email');
    }
    $this->post('/admin/login', ['email' => 'ádmin@example.test', 'password' => 'Wrong-Test-Password9!'])->assertStatus(429);
})->group('database-integration');

test('production rejects cache stores that cannot share login locks', function () {
    foreach (['array', 'null', 'session'] as $driver) {
        $this->app->instance('env', 'production');
        config(['cache.default' => 'unsafe', 'cache.stores.unsafe' => ['driver' => $driver]]);
        expect(fn () => (new AppServiceProvider($this->app))->boot())
            ->toThrow(LogicException::class, 'must share atomic locks');
    }
    config(['cache.default' => 'database']);
    expect(fn () => (new AppServiceProvider($this->app))->boot())->not->toThrow(LogicException::class);

    foreach (['local', 'testing'] as $environment) {
        foreach (['array', 'null', 'session'] as $driver) {
            $this->app->instance('env', $environment);
            config(['cache.default' => 'unsafe', 'cache.stores.unsafe' => ['driver' => $driver]]);
            expect(fn () => (new AppServiceProvider($this->app))->boot())->not->toThrow(LogicException::class);
        }
    }
    config(['cache.default' => 'array']);
});

test('production requires a finite PHP execution limit for login locks', function () {
    $previous = ini_get('max_execution_time');
    $this->app->instance('env', 'production');
    ini_set('max_execution_time', '0');
    try {
        expect(fn () => app(LoginRateLimit::class)->attempt('admin@example.test', '127.0.0.1', fn (): bool => false))
            ->toThrow(LogicException::class, 'max_execution_time');
    } finally {
        ini_set('max_execution_time', (string) $previous);
        $this->app->instance('env', 'testing');
    }
});

test('admission lock contention is not reported as a rate limit throttle', function () {
    $existingEmail = 'admin@example.test';
    User::factory()->create(['email' => $existingEmail]);
    $hasher = app('hash');
    foreach ([[$existingEmail, 'check'], ['unknown@example.test', 'driver']] as [$email, $hashMethod]) {
        $lock = Cache::lock(LoginRateLimit::admissionKey($email, '127.0.0.1'), 10);
        expect($lock->get())->toBeTrue();
        try {
            Hash::spy();
            $response = $this->post('/admin/login', ['email' => $email, 'password' => 'Wrong-Test-Password9!']);
            $response
                ->assertStatus(503)
                ->assertHeaderMissing('Retry-After')
                ->assertContent('');
            Hash::shouldNotHaveReceived($hashMethod);
        } finally {
            Hash::swap($hasher);
            Auth::forgetGuards();
            $lock->release();
        }
    }
    $this->post('/admin/login', ['email' => $existingEmail, 'password' => 'Wrong-Test-Password9!'])->assertSessionHasErrors('email');
});

test('admission lock contention returns an empty unavailable response', function () {
    $email = 'admin@example.test';
    $lock = Cache::lock(LoginRateLimit::admissionKey($email, '127.0.0.1'), 10);
    expect($lock->get())->toBeTrue();

    try {
        $response = $this->post('/admin/login', ['email' => $email, 'password' => 'Wrong-Test-Password9!']);

        $response
            ->assertStatus(503)
            ->assertHeaderMissing('Retry-After')
            ->assertContent('');
        $this->assertGuest('web');
    } finally {
        $lock->release();
    }
});

test('login lock verification command reports contention between independent processes', function () {
    $this->artisan('admin:verify-login-rate-limit-lock')
        ->expectsOutput('The login rate limit verification lock was acquired and released.')
        ->assertSuccessful();
    $this->artisan('admin:verify-login-rate-limit-lock', ['--hold' => '31'])
        ->expectsOutput('The --hold option must be an integer between 0 and 30.')
        ->assertFailed();
    $database = tempnam(sys_get_temp_dir(), 'kitsune-login-lock-');
    $environment = [
        'APP_ENV' => 'testing',
        'CACHE_STORE' => 'database',
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => $database,
        'DB_CACHE_CONNECTION' => 'sqlite',
        'DB_CACHE_LOCK_CONNECTION' => 'sqlite',
    ];
    $holder = null;
    try {
        (new Process([PHP_BINARY, base_path('artisan'), 'migrate:fresh', '--force'], base_path(), $environment, timeout: 30))->mustRun();
        $holder = new Process([PHP_BINARY, base_path('artisan'), 'admin:verify-login-rate-limit-lock', '--hold=2'], base_path(), $environment, timeout: 10);
        $holder->start();
        $deadline = microtime(true) + 5;
        do {
            if (str_contains($holder->getOutput(), 'Holding the login rate limit verification lock')) {
                break;
            }
            usleep(20000);
        } while (microtime(true) < $deadline);
        expect($holder->getOutput())->toContain('Holding the login rate limit verification lock');
        $contender = new Process([PHP_BINARY, base_path('artisan'), 'admin:verify-login-rate-limit-lock'], base_path(), $environment, timeout: 10);
        expect($contender->run())->toBe(1)
            ->and($contender->getOutput())->toContain('already held')
            ->and($holder->wait())->toBe(0)
            ->and($holder->getOutput())->toContain('acquired and released');
    } finally {
        $holder?->stop();
        if (is_file($database)) {
            unlink($database);
        }
    }
})->group('database-integration');
