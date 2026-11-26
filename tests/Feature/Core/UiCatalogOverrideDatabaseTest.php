<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Core\Localization\Services\UiCatalogOverrideManager;

uses(RefreshDatabase::class);

function overrideRow(array $overrides = []): array
{
    $now = now();

    return array_merge([
        'locale' => 'en',
        'key' => 'core::language.installed',
        'value' => 'Language :locale installed.',
        'created_by' => 'console',
        'updated_by' => 'console',
        'created_at' => $now,
        'updated_at' => $now,
    ], $overrides);
}

test('override storage enforces locale keys uniqueness and the global state singleton', function () {
    $state = DB::table('ui_catalog_override_state')->sole();

    expect((bool) $state->enabled)->toBeTrue()
        ->and((int) $state->version)->toBe(1)
        ->and($state->updated_by)->toBe('system');

    DB::table('ui_catalog_overrides')->insert(overrideRow());

    $row = DB::table('ui_catalog_overrides')->sole();
    expect($row->key)->toBe('core::language.installed')
        ->and($row->value)->toBe('Language :locale installed.')
        ->and($row->created_by)->toBe('console');

    expect(fn () => DB::table('ui_catalog_overrides')->insert(overrideRow()))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('ui_catalog_overrides')->insert(overrideRow(['locale' => 'fr_FR'])))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('ui_catalog_override_state')->insert([
            'id' => 2,
            'enabled' => true,
            'version' => 1,
            'updated_by' => 'console',
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class)
        ->and(fn () => DB::table('ui_catalog_override_state')->where('id', 1)->delete())
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('ui_catalog_override_state')->where('id', 1)->update(['id' => 2]))
        ->toThrow(QueryException::class);

    DB::table('ui_catalog_override_state')->where('id', 1)->update([
        'version' => 2,
        'enabled' => false,
        'updated_by' => 'console',
        'updated_at' => now(),
    ]);

    expect(DB::table('ui_catalog_override_state')->where('id', 1)->value('version'))->toBe(2)
        ->and((bool) DB::table('ui_catalog_override_state')->where('id', 1)->value('enabled'))->toBeFalse();
})->group('database-integration');

test('the global override switch updates enabled and version through the manager', function () {
    $manager = app(UiCatalogOverrideManager::class);

    expect($manager->toggle(false))
        ->toMatchArray(['changed' => true, 'enabled' => false, 'version' => 2])
        ->and((bool) DB::table('ui_catalog_override_state')->where('id', 1)->value('enabled'))->toBeFalse()
        ->and((int) DB::table('ui_catalog_override_state')->where('id', 1)->value('version'))->toBe(2);

    expect($manager->toggle(false))
        ->toMatchArray(['changed' => false, 'enabled' => false, 'version' => 2]);

    expect($manager->toggle(true))
        ->toMatchArray(['changed' => true, 'enabled' => true, 'version' => 3])
        ->and((bool) DB::table('ui_catalog_override_state')->where('id', 1)->value('enabled'))->toBeTrue()
        ->and((int) DB::table('ui_catalog_override_state')->where('id', 1)->value('version'))->toBe(3);
})->group('database-integration');
