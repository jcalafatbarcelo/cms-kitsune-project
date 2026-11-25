<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Core\Localization\Models\UiCatalogOverride;
use Modules\Core\Localization\Models\UiCatalogOverrideState;
use Modules\Core\Localization\Services\UiCatalogActorResolver;
use Modules\Core\Localization\Services\UiCatalogOverrideManager;
use Modules\Core\Localization\Services\UiCatalogOverrides;
use Modules\Core\Localization\Services\UiCatalogOverrideStore;
use Modules\Core\Localization\Services\UiCatalogOverrideValidator;
use Modules\Core\Localization\Services\UiCatalogOwnerResolver;

uses(RefreshDatabase::class);

function overrideCommandInstallLanguage(string $locale = 'es_ES', string $prefix = 'es-es'): void
{
    $now = now();
    DB::table('languages')->insert([
        'locale' => $locale,
        'url_prefix' => $prefix,
        'name' => $locale,
        'native_name' => $locale,
        'text_direction' => 'ltr',
        'is_active' => true,
        'is_url_general' => false,
        'installed_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

function overrideCommandRemoveTree(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $entry) {
        $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }

    rmdir($path);
}

beforeEach(function () {
    overrideCommandInstallLanguage();
});

test('set requires an explicit value and persists only valid overrides', function () {
    $this->artisan('cms:ui-catalog:override:set', [
        'key' => 'core::language.installed',
        'locale' => 'es_ES',
    ])->expectsOutputToContain('--value')->assertFailed();

    expect(UiCatalogOverride::query()->count())->toBe(0);

    $this->artisan('cms:ui-catalog:override:set', [
        'key' => 'core::language.installed',
        'locale' => 'es_ES',
        '--value' => 'Idioma :locale instalado.',
    ])->expectsOutputToContain('created')->assertSuccessful();

    expect(UiCatalogOverride::query()->sole()->value)->toBe('Idioma :locale instalado.')
        ->and(UiCatalogOverrideState::query()->find(1)->version)->toBe(2);

    $this->artisan('cms:ui-catalog:override:set', [
        'key' => 'core::language.installed',
        'locale' => 'es_ES',
        '--value' => 'Idioma instalado.',
    ])->assertFailed();

    expect(UiCatalogOverride::query()->sole()->value)->toBe('Idioma :locale instalado.')
        ->and(UiCatalogOverrideState::query()->find(1)->version)->toBe(2);

    $this->artisan('cms:ui-catalog:override:set', [
        'key' => 'core::language.installed',
        'locale' => 'es_ES',
        '--value' => 'Idioma alternativo :locale instalado.',
    ])->expectsOutputToContain('updated')->assertSuccessful();

    expect(UiCatalogOverride::query()->sole()->value)->toBe('Idioma alternativo :locale instalado.')
        ->and(UiCatalogOverrideState::query()->find(1)->version)->toBe(3);
});

test('list hides values by default and supports filters', function () {
    $manager = app(UiCatalogOverrideManager::class);
    $manager->set('core::language.installed', 'es_ES', 'Idioma :locale instalado.');

    $this->artisan('cms:ui-catalog:override:list')
        ->expectsOutputToContain('core::language.installed')
        ->doesntExpectOutputToContain('Idioma :locale instalado.')
        ->assertSuccessful();

    $this->artisan('cms:ui-catalog:override:list', ['--with-values' => true])
        ->expectsOutputToContain('Idioma :locale instalado.')
        ->assertSuccessful();

    $this->artisan('cms:ui-catalog:override:list', ['--locale' => 'es_ES', '--owner' => 'core'])
        ->expectsOutputToContain('core::language.installed')
        ->assertSuccessful();

    $this->artisan('cms:ui-catalog:override:list', ['--owner' => 'Core'])->assertFailed();
    $this->artisan('cms:ui-catalog:override:list', ['--locale' => '../en'])->assertFailed();
});

test('remove deletes existing overrides and fails for missing ones', function () {
    app(UiCatalogOverrideManager::class)->set('core::language.installed', 'es_ES', 'Idioma :locale instalado.');

    $this->artisan('cms:ui-catalog:override:remove', [
        'key' => 'core::language.installed',
        'locale' => 'es_ES',
    ])->expectsOutputToContain('removed')->assertSuccessful();

    expect(UiCatalogOverride::query()->count())->toBe(0)
        ->and(UiCatalogOverrideState::query()->find(1)->version)->toBe(3);

    $this->artisan('cms:ui-catalog:override:remove', [
        'key' => 'core::language.installed',
        'locale' => 'es_ES',
    ])->assertFailed();

    $this->artisan('cms:ui-catalog:override:remove', [
        'key' => 'Core::language.installed',
        'locale' => 'es_ES',
    ])->assertFailed();
});

test('enable and disable toggle the global switch without incrementing unchanged states', function () {
    app(UiCatalogOverrideManager::class)->set('core::language.installed', 'es_ES', 'Idioma :locale instalado.');

    $this->artisan('cms:ui-catalog:override:disable')
        ->expectsOutputToContain('disabled')
        ->assertSuccessful();
    expect(UiCatalogOverrideState::query()->find(1)->enabled)->toBeFalse()
        ->and(UiCatalogOverrideState::query()->find(1)->version)->toBe(3)
        ->and(UiCatalogOverride::query()->count())->toBe(1);

    $this->artisan('cms:ui-catalog:override:disable')
        ->expectsOutputToContain('already disabled')
        ->assertSuccessful();
    expect(UiCatalogOverrideState::query()->find(1)->version)->toBe(3);

    $this->artisan('cms:ui-catalog:override:enable')
        ->expectsOutputToContain('enabled')
        ->assertSuccessful();
    expect(UiCatalogOverrideState::query()->find(1)->enabled)->toBeTrue()
        ->and(UiCatalogOverrideState::query()->find(1)->version)->toBe(4);

    $this->artisan('cms:ui-catalog:override:enable')
        ->expectsOutputToContain('already enabled')
        ->assertSuccessful();
    expect(UiCatalogOverrideState::query()->find(1)->version)->toBe(4);
});

test('validate reports invalid stored values without mutating data', function () {
    $manager = app(UiCatalogOverrideManager::class);
    $manager->set('core::language.installed', 'es_ES', 'Idioma :locale instalado.');

    $this->artisan('cms:ui-catalog:override:validate')
        ->expectsOutputToContain('All UI catalog overrides are valid.')
        ->assertSuccessful();

    DB::table('ui_catalog_overrides')->update(['value' => '<strong>Idioma</strong>']);

    $this->artisan('cms:ui-catalog:override:validate')
        ->expectsOutputToContain('invalid value')
        ->assertFailed();

    expect(UiCatalogOverride::query()->sole()->value)->toBe('<strong>Idioma</strong>')
        ->and(UiCatalogOverrideState::query()->find(1)->version)->toBe(2);
});

test('validate reports orphaned owners and keys without deleting rows', function () {
    $directory = storage_path('framework/testing/ui-override-command-'.bin2hex(random_bytes(6)));
    mkdir($directory, recursive: true);
    $catalog = $directory.DIRECTORY_SEPARATOR.'en.json';
    file_put_contents($catalog, json_encode(['core::greeting' => 'Hello'], JSON_THROW_ON_ERROR));

    $bind = function () use ($directory): UiCatalogOverrideManager {
        $owners = new UiCatalogOwnerResolver($directory, base_path('Templates'));
        $validator = new UiCatalogOverrideValidator($owners);
        $overrides = new UiCatalogOverrides(
            app(UiCatalogOverrideStore::class),
            $validator,
            app('log'),
        );
        $manager = new UiCatalogOverrideManager($validator, app(UiCatalogActorResolver::class), $overrides);

        app()->instance(UiCatalogOwnerResolver::class, $owners);
        app()->instance(UiCatalogOverrideValidator::class, $validator);
        app()->instance(UiCatalogOverrides::class, $overrides);
        app()->instance(UiCatalogOverrideManager::class, $manager);

        return $manager;
    };

    try {
        $bind()->set('core::greeting', 'es_ES', 'Hola');

        file_put_contents($catalog, json_encode(['core::other' => 'Other'], JSON_THROW_ON_ERROR));
        $bind();

        $this->artisan('cms:ui-catalog:override:validate')
            ->expectsOutputToContain('key missing')
            ->assertFailed();

        expect(UiCatalogOverride::query()->count())->toBe(1)
            ->and(UiCatalogOverrideState::query()->find(1)->version)->toBe(2);

        unlink($catalog);
        $bind();

        $this->artisan('cms:ui-catalog:override:validate')
            ->expectsOutputToContain('owner unavailable')
            ->assertFailed();

        expect(UiCatalogOverride::query()->count())->toBe(1)
            ->and(UiCatalogOverrideState::query()->find(1)->version)->toBe(2);
    } finally {
        overrideCommandRemoveTree($directory);
    }
});
