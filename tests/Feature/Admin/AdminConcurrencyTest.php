<?php

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

test('concurrent bootstraps contend on one singleton and create no orphan users', function () {
    $connection = config('database.default');
    $original = config('database.connections.'.$connection);
    $file = null;
    $workers = [];
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

        $workers = [];
        for ($i = 0; $i < 3; $i++) {
            $worker = new Process([PHP_BINARY, base_path('tests/Fixtures/admin-concurrency-worker.php')], base_path(), ['APP_ENV' => 'testing'], timeout: 30);
            $worker->setInput(json_encode(['connection' => config('database.connections.'.$connection), 'operation' => 'login'], JSON_THROW_ON_ERROR));
            $worker->start();
            $workers[] = $worker;
        }
        $admitted = 0;
        foreach ($workers as $worker) {
            expect($worker->wait())->toBe(0);
            $admitted += substr_count($worker->getOutput(), 'ADMITTED');
        }
        expect($admitted)->toBe(5);
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
    }
})->group('database-integration');
