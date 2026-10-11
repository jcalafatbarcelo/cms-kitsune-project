<?php

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

test('concurrent bootstraps contend on one singleton and create no orphan users', function () {
    $connection = config('database.default');
    $original = config('database.connections.'.$connection);
    $file = null;
    $workers = [];
    $verificationLog = null;
    $barrier = null;
    $slowLog = null;
    if ($original['driver'] === 'sqlite') {
        $file = tempnam(sys_get_temp_dir(), 'kitsune-admin-');
        config(['database.connections.'.$connection.'.database' => $file, 'database.connections.'.$connection.'.busy_timeout' => 5000]);
    }
    DB::purge($connection);
    try {
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        DB::beginTransaction();
        DB::table('administration_access')->where('singleton', 1)->update(['singleton' => 1]);
        foreach (['first@example.test', 'second@example.test'] as $email) {
            $worker = new Process([PHP_BINARY, base_path('tests/Fixtures/admin-concurrency-worker.php')], base_path(), ['APP_ENV' => 'testing'], timeout: 30);
            $worker->setInput(json_encode(['connection' => config('database.connections.'.$connection), 'email' => $email], JSON_THROW_ON_ERROR));
            $worker->start();
            $workers[] = $worker;
        }
        $deadline = microtime(true) + 15;
        do {
            $ready = array_filter($workers, fn (Process $worker) => str_contains($worker->getOutput(), 'READY'));
            if (count($ready) === 2) {
                break;
            }
            usleep(20000);
        } while (microtime(true) < $deadline);
        expect(count($ready))->toBe(2);
        usleep(200000);
        foreach ($workers as $worker) {
            expect($worker->isRunning())->toBeTrue();
        }
        DB::commit();
        $results = [];
        foreach ($workers as $worker) {
            expect($worker->wait())->toBe(0);
            $results[] = trim(str_replace('READY', '', $worker->getOutput()));
        }
        sort($results);
        expect($results)->toBe(['CREATED', 'EXISTS'])
            ->and(DB::table('users')->count())->toBe(1)
            ->and(DB::table('administration_access')->sole()->super_admin_user_id)->not->toBeNull();
        $adminEmail = DB::table('administration_access')
            ->join('users', 'users.id', '=', 'administration_access.super_admin_user_id')
            ->value('users.email');

        $verificationLog = tempnam(sys_get_temp_dir(), 'kitsune-login-verifications-');
        $barrier = tempnam(sys_get_temp_dir(), 'kitsune-login-barrier-');
        $csrf = new Process([PHP_BINARY, base_path('tests/Fixtures/admin-concurrency-worker.php')], base_path(), ['APP_ENV' => 'testing'], timeout: 30);
        $csrf->setInput(json_encode([
            'connection' => config('database.connections.'.$connection),
            'operation' => 'csrf-invalid',
            'email' => $adminEmail,
            'password' => 'Wrong-Test-Password9!',
        ], JSON_THROW_ON_ERROR));
        expect($csrf->run())->toBe(0)->and($csrf->getOutput())->toContain('STATUS:419');

        $workers = [];
        for ($i = 0; $i < 10; $i++) {
            $worker = new Process([PHP_BINARY, base_path('tests/Fixtures/admin-concurrency-worker.php')], base_path(), ['APP_ENV' => 'testing'], timeout: 30);
            $worker->setInput(json_encode([
                'connection' => config('database.connections.'.$connection),
                'operation' => 'login',
                'email' => $adminEmail,
                'password' => 'Wrong-Test-Password9!',
                'barrier' => $barrier,
                'verification_log' => $verificationLog,
            ], JSON_THROW_ON_ERROR));
            $worker->start();
            $workers[] = $worker;
        }
        $deadline = microtime(true) + 10;
        do {
            if (substr_count((string) file_get_contents($barrier), "ready\n") === 10) {
                break;
            }
            usleep(20000);
        } while (microtime(true) < $deadline);
        expect(substr_count((string) file_get_contents($barrier), "ready\n"))->toBe(10);
        file_put_contents($barrier, 'release');
        $admitted = 0;
        $throttled = 0;
        foreach ($workers as $worker) {
            expect($worker->wait())->toBe(0);
            $admitted += substr_count($worker->getOutput(), 'STATUS:302');
            $throttled += substr_count($worker->getOutput(), 'STATUS:429');
        }
        expect($admitted)->toBe(5)
            ->and($throttled)->toBe(5)
            ->and(count(file($verificationLog, FILE_IGNORE_NEW_LINES)))->toBe(5);
        $reset = new Process([PHP_BINARY, base_path('tests/Fixtures/admin-concurrency-worker.php')], base_path(), ['APP_ENV' => 'testing'], timeout: 30);
        $reset->setInput(json_encode([
            'connection' => config('database.connections.'.$connection),
            'operation' => 'reset-login',
            'email' => $adminEmail,
        ], JSON_THROW_ON_ERROR));
        expect($reset->run())->toBe(0);

        $slowLog = tempnam(sys_get_temp_dir(), 'kitsune-login-slow-');
        $first = new Process([PHP_BINARY, base_path('tests/Fixtures/admin-concurrency-worker.php')], base_path(), ['APP_ENV' => 'testing'], timeout: 30);
        $first->setInput(json_encode([
            'connection' => config('database.connections.'.$connection),
            'operation' => 'slow-login',
            'email' => $adminEmail,
            'password' => 'Wrong-Test-Password9!',
            'log' => $slowLog,
            'label' => 'first',
        ], JSON_THROW_ON_ERROR));
        $first->start();
        $deadline = microtime(true) + 5;
        do {
            if (str_contains($first->getOutput(), 'VERIFYING')) {
                break;
            }
            usleep(20000);
        } while (microtime(true) < $deadline);
        expect($first->getOutput())->toContain('VERIFYING');
        usleep(10500000);
        $second = new Process([PHP_BINARY, base_path('tests/Fixtures/admin-concurrency-worker.php')], base_path(), ['APP_ENV' => 'testing'], timeout: 30);
        $second->setInput(json_encode([
            'connection' => config('database.connections.'.$connection),
            'operation' => 'slow-login',
            'email' => $adminEmail,
            'password' => 'Wrong-Test-Password9!',
            'log' => $slowLog,
            'label' => 'second',
        ], JSON_THROW_ON_ERROR));
        $second->start();
        expect($first->wait())->toBe(0)->and($second->wait())->toBe(0);
        $events = array_map(fn (string $line): array => explode(':', $line), file($slowLog, FILE_IGNORE_NEW_LINES));
        $starts = [];
        $ends = [];
        foreach ($events as [$label, $event, $time]) {
            ${$event}[$label] = (float) $time;
        }
        expect($starts['second'])->toBeGreaterThanOrEqual($ends['first']);
        $migration = require database_path('migrations/2026_10_02_000000_create_administration_access_table.php');
        $migration->down();
        expect(DB::getSchemaBuilder()->hasTable('administration_access'))->toBeFalse()->and(DB::table('users')->count())->toBe(1);
        $migration->up();
        expect(DB::table('administration_access')->sole()->super_admin_user_id)->toBeNull();
    } finally {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        foreach ($workers as $worker) {
            $worker->stop();
        }
        DB::purge($connection);
        config(['database.connections.'.$connection => $original]);
        RefreshDatabaseState::$migrated = false;
        if ($file !== null) {
            foreach ([$file, $file.'-wal', $file.'-shm'] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
        foreach ([$verificationLog, $barrier, $slowLog] as $path) {
            if (is_string($path) && is_file($path)) {
                unlink($path);
            }
        }
    }
})->group('database-integration');
