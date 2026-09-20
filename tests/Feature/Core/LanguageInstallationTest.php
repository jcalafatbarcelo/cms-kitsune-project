<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('a clean installation creates the base language and enforces the settings singleton', function () {
    $connection = DB::connection();
    $driver = $connection->getDriverName();
    $version = match ($driver) {
        'sqlite' => (string) $connection->scalar('select sqlite_version()'),
        'mysql', 'mariadb' => (string) $connection->scalar('select version()'),
        default => throw new RuntimeException("Unsupported database driver [$driver]."),
    };

    if ($driver === 'sqlite') {
        expect(version_compare($version, '3.45.0', '>='))->toBeTrue();
    } elseif ($driver === 'mysql') {
        expect($version)->toStartWith('8.4.')
            ->and(str_contains(strtolower($version), 'mariadb'))->toBeFalse();
    } else {
        expect($version)->toStartWith('11.4.')
            ->and(str_contains(strtolower($version), 'mariadb'))->toBeTrue();
    }

    $language = DB::table('languages')->where('locale', 'en')->first();
    $settings = DB::table('language_settings')->sole();

    expect($language)->not->toBeNull()
        ->and((bool) $language->is_active)->toBeTrue()
        ->and($settings->id)->toBe(1)
        ->and($settings->base_language_id)->toBe($language->id)
        ->and($settings->frontend_default_language_id)->toBe($language->id)
        ->and($settings->backoffice_default_language_id)->toBe($language->id);

    $secondLanguageId = DB::table('languages')->insertGetId([
        'locale' => 'es_ES',
        'name' => 'Spanish (Spain)',
        'native_name' => 'Español (España)',
        'text_direction' => 'ltr',
        'is_active' => true,
        'installed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => DB::table('language_settings')->insert([
        'id' => 2,
        'base_language_id' => $secondLanguageId,
        'frontend_default_language_id' => $language->id,
        'backoffice_default_language_id' => $language->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('language_settings')->where('id', 1)->update(['id' => 2]))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('languages')->insert([
            'locale' => 'ar',
            'name' => 'Arabic',
            'native_name' => 'Arabic',
            'text_direction' => 'invalid',
            'is_active' => false,
            'installed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class)
        ->and(fn () => DB::table('languages')->where('id', $language->id)->delete())
        ->toThrow(QueryException::class)
        ->and(DB::table('language_settings')->count())->toBe(1);
})->group('database-integration');
