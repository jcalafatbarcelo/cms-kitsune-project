<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Language\Models\LanguageSetting;
use Modules\Core\Localization\Services\UiCatalogActorResolver;
use Modules\Core\Localization\Services\UiCatalogOverrideManager;
use Modules\Core\Localization\Services\UiCatalogOverrides;
use Modules\Core\Localization\Services\UiCatalogOverrideStore;
use Modules\Core\Localization\Services\UiCatalogOverrideValidator;
use Modules\Core\Localization\Services\UiCatalogOwnerResolver;
use Modules\Core\Localization\Services\UiCatalogRepository;
use Modules\Core\Localization\Services\UiCatalogResolver;
use Modules\Core\Template\Models\CmsTemplate;
use Modules\Core\Template\Services\TemplateUiCatalogs;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

uses(RefreshDatabase::class);

final class CountingOverrideOwnerResolver extends UiCatalogOwnerResolver
{
    public int $reads = 0;

    protected function readSnapshot(string $path, string $owner): ?array
    {
        $this->reads++;

        return parent::readSnapshot($path, $owner);
    }
}

function overrideResolutionInstallLanguage(string $locale = 'es_ES', string $prefix = 'es-es', bool $active = true): void
{
    $now = now();
    DB::table('languages')->insert([
        'locale' => $locale,
        'url_prefix' => $prefix,
        'name' => $locale,
        'native_name' => $locale,
        'text_direction' => 'ltr',
        'is_active' => $active,
        'is_url_general' => false,
        'installed_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

function overrideResolutionCoreResolver(string $mode): UiCatalogResolver
{
    return new UiCatalogResolver(
        new UiCatalogRepository((string) config('core.catalog_path')),
        $mode,
        new NullLogger,
        overrides: app(UiCatalogOverrides::class),
    );
}

function overrideResolutionCoreResolverWith(UiCatalogOverrides $overrides, string $mode): UiCatalogResolver
{
    return overrideResolutionCatalogResolver((string) config('core.catalog_path'), $mode, $overrides);
}

function overrideResolutionCatalogResolver(string $catalogPath, string $mode, UiCatalogOverrides $overrides): UiCatalogResolver
{
    return new UiCatalogResolver(
        new UiCatalogRepository($catalogPath, 'core'),
        $mode,
        new NullLogger,
        overrides: $overrides,
    );
}

function overrideResolutionRemoveTree(string $path): void
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
    overrideResolutionInstallLanguage();
    overrideResolutionInstallLanguage('fr_FR', 'fr', false);
});

test('core resolution applies overrides local catalogs and base fallback in order', function () {
    $manager = app(UiCatalogOverrideManager::class);
    $manager->set('core::language.installed', 'es_ES', 'Idioma alternativo :locale instalado.');

    $resolver = overrideResolutionCoreResolver('base');

    expect($resolver->get('core::language.installed', 'es_ES', ['locale' => 'es_ES']))
        ->toBe('Idioma alternativo es_ES instalado.')
        ->and($resolver->get('core::language.installed', 'fr_FR', ['locale' => 'fr_FR']))
        ->toBe('Language fr_FR installed.');

    $manager->set('core::language.installed', 'fr_FR', 'Langue :locale installée.');
    $resolver = overrideResolutionCoreResolver('key');

    expect($resolver->get('core::language.installed', 'fr_FR', ['locale' => 'fr_FR']))
        ->toBe('Langue fr_FR installée.');

    $manager->remove('core::language.installed', 'fr_FR');

    expect($resolver->get('core::language.installed', 'fr_FR', ['locale' => 'fr_FR']))
        ->toBe('core::language.installed')
        ->and(overrideResolutionCoreResolver('base')->get('core::language.installed', 'fr_FR', ['locale' => 'fr_FR']))
        ->toBe('Language fr_FR installed.');
});

test('the global switch disables and restores overrides without deleting them', function () {
    $manager = app(UiCatalogOverrideManager::class);
    $manager->set('core::language.installed', 'es_ES', 'Idioma alternativo :locale instalado.');
    $resolver = overrideResolutionCoreResolver('base');

    expect($resolver->get('core::language.installed', 'es_ES', ['locale' => 'es_ES']))
        ->toBe('Idioma alternativo es_ES instalado.');

    $manager->toggle(false);

    expect($resolver->get('core::language.installed', 'es_ES', ['locale' => 'es_ES']))
        ->toBe('Idioma es_ES instalado.');

    $manager->toggle(true);

    expect($resolver->get('core::language.installed', 'es_ES', ['locale' => 'es_ES']))
        ->toBe('Idioma alternativo es_ES instalado.');
});

test('choice applies the plural selector to an override line', function () {
    $directory = storage_path('framework/testing/ui-override-choice-'.bin2hex(random_bytes(6)));
    mkdir($directory, recursive: true);
    file_put_contents($directory.DIRECTORY_SEPARATOR.'en.json', json_encode([
        'core::items.count' => '{0} No items|{1} One item|[2,*] :count items',
    ], JSON_THROW_ON_ERROR));

    try {
        $owners = new UiCatalogOwnerResolver($directory, base_path('Templates'));
        $validator = new UiCatalogOverrideValidator($owners);
        $overrides = new UiCatalogOverrides(app(UiCatalogOverrideStore::class), $validator, new NullLogger);
        $manager = new UiCatalogOverrideManager(
            $validator,
            app(UiCatalogActorResolver::class),
            $overrides,
        );

        $manager->set('core::items.count', 'es_ES', '{0} Sin elementos|{1} Un elemento|[2,*] :count elementos');

        $resolver = new UiCatalogResolver(
            new UiCatalogRepository($directory, 'core'),
            'base',
            new NullLogger,
            overrides: $overrides,
        );

        expect($resolver->choice('core::items.count', 3, 'es_ES'))->toBe('3 elementos')
            ->and($resolver->choice('core::items.count', 1, 'es_ES'))->toBe('Un elemento');
    } finally {
        overrideResolutionRemoveTree($directory);
    }
});

test('template catalogs apply overrides before their files and keep owner boundaries', function () {
    config(['core.ui_catalog_fallback_mode' => 'key']);
    $manager = app(UiCatalogOverrideManager::class);
    $catalogs = app(TemplateUiCatalogs::class);
    $base = CmsTemplate::query()->where('identifier', 'base')->sole();

    expect($catalogs->text($base, $base, 'admin.login.title', 'es_ES'))
        ->toBe('Acceso de administración');

    $manager->set('base::admin.login.title', 'es_ES', 'Acceso alternativo');
    $manager->set('base::admin.login.title', 'fr_FR', 'Acceso alternativo fr');

    expect($catalogs->text($base, $base, 'admin.login.title', 'es_ES'))
        ->toBe('Acceso alternativo')
        ->and($catalogs->text($base, $base, 'admin.login.title', 'fr_FR'))
        ->toBe('Acceso alternativo fr');

    config(['core.ui_catalog_fallback_mode' => 'base']);

    expect($catalogs->text($base, $base, 'admin.login.title', 'fr_FR'))
        ->toBe('Acceso alternativo fr');

    config(['core.ui_catalog_fallback_mode' => 'key']);

    $manager->remove('base::admin.login.title', 'es_ES');
    $manager->remove('base::admin.login.title', 'fr_FR');

    expect($catalogs->text($base, $base, 'admin.login.title', 'fr_FR'))
        ->toBe('admin.login.title');

    config(['core.ui_catalog_fallback_mode' => 'base']);

    expect($catalogs->text($base, $base, 'admin.login.title', 'fr_FR'))
        ->toBe('Administrator sign in');
});

test('a base override does not substitute a different template key', function () {
    $directory = storage_path('framework/testing/ui-override-owner-'.bin2hex(random_bytes(6)));
    $lang = $directory.DIRECTORY_SEPARATOR.'Acme'.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'lang';
    mkdir($lang, recursive: true);
    file_put_contents($lang.DIRECTORY_SEPARATOR.'en.json', json_encode([
        'acme::admin.login.title' => 'Acme sign in',
    ], JSON_THROW_ON_ERROR));

    try {
        config(['core.ui_catalog_fallback_mode' => 'base']);
        app(UiCatalogOverrideManager::class)->set('base::admin.login.title', 'es_ES', 'Base override');

        $acme = new CmsTemplate(['identifier' => 'acme', 'directory' => 'Acme']);
        $catalogs = new TemplateUiCatalogs($directory, new NullLogger, app(UiCatalogOverrides::class));

        expect($catalogs->text($acme, $acme, 'admin.login.title', 'es_ES'))->toBe('Acme sign in');
    } finally {
        overrideResolutionRemoveTree($directory);
    }
});

test('a committed mutation is visible in the same process and in a new resolution instance', function () {
    $manager = app(UiCatalogOverrideManager::class);
    $resolver = overrideResolutionCoreResolver('base');

    $manager->set('core::language.installed', 'es_ES', 'Primero :locale');
    expect($resolver->get('core::language.installed', 'es_ES', ['locale' => 'es_ES']))->toBe('Primero es_ES');

    $manager->set('core::language.installed', 'es_ES', 'Segundo :locale');
    expect($resolver->get('core::language.installed', 'es_ES', ['locale' => 'es_ES']))->toBe('Segundo es_ES');

    app()->forgetInstance(UiCatalogOverrides::class);
    app()->forgetInstance(UiCatalogOverrideManager::class);

    $manager = app(UiCatalogOverrideManager::class);

    expect(overrideResolutionCoreResolver('base')->get('core::language.installed', 'es_ES', ['locale' => 'es_ES']))
        ->toBe('Segundo es_ES');

    $manager->remove('core::language.installed', 'es_ES');

    expect(overrideResolutionCoreResolver('base')->get('core::language.installed', 'es_ES', ['locale' => 'es_ES']))
        ->toBe('Idioma es_ES instalado.');
});

test('an override failure in cache falls back to direct storage reads', function () {
    app(UiCatalogOverrideManager::class)->set('core::language.installed', 'es_ES', 'Idioma alternativo :locale instalado.');

    Cache::shouldReceive('get')->andThrow(new RuntimeException('cache down'));
    Cache::shouldReceive('put')->andThrow(new RuntimeException('cache down'));

    $resolver = overrideResolutionCoreResolver('base');

    expect($resolver->get('core::language.installed', 'es_ES', ['locale' => 'es_ES']))
        ->toBe('Idioma alternativo es_ES instalado.');
});

test('the login and public pages apply stored overrides over the localized catalogs', function () {
    $spanishId = DB::table('languages')->where('locale', 'es_ES')->value('id');
    LanguageSetting::query()->whereKey(1)->update(['backoffice_default_language_id' => $spanishId]);

    $manager = app(UiCatalogOverrideManager::class);
    $manager->set('base::admin.login.title', 'es_ES', 'Acceso verificado');
    $manager->set('base::page.home.under-construction.heading', 'en', 'Construcción en curso');

    $this->get('/admin/login')->assertOk()->assertSee('Acceso verificado');
    $this->get('/')->assertOk()->assertSee('Construcción en curso');
});

test('owner catalog changes invalidate hot override caches without a version bump', function () {
    $directory = storage_path('framework/testing/ui-override-fingerprint-'.bin2hex(random_bytes(6)));
    mkdir($directory, recursive: true);
    $catalog = $directory.DIRECTORY_SEPARATOR.'en.json';

    $write = function (array $lines) use ($catalog): void {
        file_put_contents($catalog, json_encode($lines, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    };

    try {
        $write([
            'core::greeting' => 'Hello :name',
            'core::other' => 'Other',
        ]);

        $owners = new UiCatalogOwnerResolver($directory, base_path('Templates'));
        $validator = new UiCatalogOverrideValidator($owners);
        $overrides = new UiCatalogOverrides(app(UiCatalogOverrideStore::class), $validator, new NullLogger);
        $manager = new UiCatalogOverrideManager($validator, app(UiCatalogActorResolver::class), $overrides);
        $manager->set('core::greeting', 'es_ES', 'Hola :name');

        $resolver = overrideResolutionCatalogResolver($directory, 'key', $overrides);

        expect($resolver->get('core::greeting', 'es_ES', ['name' => 'Ana']))->toBe('Hola Ana');

        $write([
            'core::greeting' => 'Hello :person!',
            'core::other' => 'Other',
        ]);

        expect($resolver->get('core::greeting', 'es_ES', ['name' => 'Ana']))
            ->toBe('core::greeting');

        $freshOverrides = new UiCatalogOverrides(app(UiCatalogOverrideStore::class), $validator, new NullLogger);
        $freshResolver = overrideResolutionCatalogResolver($directory, 'key', $freshOverrides);

        expect($freshResolver->get('core::greeting', 'es_ES', ['name' => 'Ana']))
            ->toBe('core::greeting');

        $write([
            'core::greeting' => 'Hello :name',
            'core::other' => 'Other',
        ]);

        expect($freshResolver->get('core::greeting', 'es_ES', ['name' => 'Ana']))->toBe('Hola Ana');

        $write(['core::other' => 'Other']);

        expect($freshResolver->get('core::greeting', 'es_ES', ['name' => 'Ana']))
            ->toBe('core::greeting');

        $version = (int) DB::table('ui_catalog_override_state')->where('id', 1)->value('version');
        $newOwners = new UiCatalogOwnerResolver($directory, base_path('Templates'));
        $newValidator = new UiCatalogOverrideValidator($newOwners);
        $newOverrides = new UiCatalogOverrides(app(UiCatalogOverrideStore::class), $newValidator, new NullLogger);
        $newResolver = overrideResolutionCatalogResolver($directory, 'key', $newOverrides);

        expect($newResolver->get('core::greeting', 'es_ES', ['name' => 'Ana']))
            ->toBe('core::greeting')
            ->and((int) DB::table('ui_catalog_override_state')->where('id', 1)->value('version'))
            ->toBe($version);

        unlink($catalog);

        expect($freshResolver->get('core::greeting', 'es_ES', ['name' => 'Ana']))
            ->toBe('core::greeting');
    } finally {
        overrideResolutionRemoveTree($directory);
    }
});

test('a malformed cached override snapshot is ignored with one bounded warning', function (mixed $cached) {
    app(UiCatalogOverrideManager::class)->set('core::language.installed', 'es_ES', 'Idioma alternativo :locale instalado.');

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('warning')
        ->once()
        ->with('A cached UI catalog override snapshot was ignored.');

    $overrides = new UiCatalogOverrides(
        app(UiCatalogOverrideStore::class),
        app(UiCatalogOverrideValidator::class),
        $logger,
    );

    Cache::shouldReceive('get')->once()->andReturn($cached);
    Cache::shouldReceive('put')->once()->andReturnTrue();

    expect(overrideResolutionCoreResolverWith($overrides, 'base')->get('core::language.installed', 'es_ES', ['locale' => 'es_ES']))
        ->toBe('Idioma alternativo es_ES instalado.');
})->with([
    'non-array entry' => ['not-an-array'],
    'non-string value' => [['core::language.installed' => ['not-a-string']]],
]);

test('an unavailable override store is queried only once per process', function () {
    $store = Mockery::mock(UiCatalogOverrideStore::class);
    $store->shouldReceive('state')->once()->andReturnNull();
    $store->shouldReceive('rowsForLocale')->never();

    $overrides = new UiCatalogOverrides($store, app(UiCatalogOverrideValidator::class), new NullLogger);
    $resolver = overrideResolutionCoreResolverWith($overrides, 'base');

    expect($resolver->get('core::language.installed', 'es_ES', ['locale' => 'es_ES']))
        ->toBe('Idioma es_ES instalado.')
        ->and($resolver->get('core::language.installed', 'es_ES', ['locale' => 'es_ES']))
        ->toBe('Idioma es_ES instalado.');
});

test('failed override row reads are not retried for every key in the same process', function () {
    $store = Mockery::mock(UiCatalogOverrideStore::class);
    $store->shouldReceive('state')->once()->andReturn(['enabled' => true, 'version' => 1]);
    $store->shouldReceive('rowsForLocale')->once()->with('es_ES')->andReturnNull();

    $overrides = new UiCatalogOverrides($store, app(UiCatalogOverrideValidator::class), new NullLogger);
    $resolver = overrideResolutionCoreResolverWith($overrides, 'base');

    expect($resolver->get('core::language.installed', 'es_ES', ['locale' => 'es_ES']))
        ->toBe('Idioma es_ES instalado.')
        ->and($resolver->get('core::language.installed', 'es_ES', ['locale' => 'es_ES']))
        ->toBe('Idioma es_ES instalado.');
});

test('forget allows a retry after an unavailable store and no retry happens before it', function () {
    $store = Mockery::mock(UiCatalogOverrideStore::class);
    $store->shouldReceive('state')
        ->twice()
        ->andReturn(null, ['enabled' => true, 'version' => 2]);
    $store->shouldReceive('rowsForLocale')
        ->once()
        ->with('es_ES')
        ->andReturn([[
            'key' => 'core::language.installed',
            'value' => 'Idioma alternativo :locale instalado.',
        ]]);

    $overrides = new UiCatalogOverrides($store, app(UiCatalogOverrideValidator::class), new NullLogger);
    $resolver = overrideResolutionCoreResolverWith($overrides, 'base');

    expect($resolver->get('core::language.installed', 'es_ES', ['locale' => 'es_ES']))
        ->toBe('Idioma es_ES instalado.')
        ->and($resolver->get('core::language.installed', 'es_ES', ['locale' => 'es_ES']))
        ->toBe('Idioma es_ES instalado.');

    $overrides->forget();

    expect($resolver->get('core::language.installed', 'es_ES', ['locale' => 'es_ES']))
        ->toBe('Idioma alternativo es_ES instalado.');
});

test('owner snapshots and template lookups are reused across keys in a process', function () {
    $root = storage_path('framework/testing/ui-override-snapshot-'.bin2hex(random_bytes(6)));
    $lang = $root.DIRECTORY_SEPARATOR.'Acme'.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'lang';
    mkdir($lang, recursive: true);
    file_put_contents($lang.DIRECTORY_SEPARATOR.'en.json', json_encode([
        'acme::greeting' => 'Hello',
        'acme::other' => 'Other',
    ], JSON_THROW_ON_ERROR));

    DB::table('cms_templates')->insert([
        'directory' => 'Acme',
        'directory_key' => 'acme',
        'identifier' => 'acme',
        'name' => 'Acme',
        'manifest_hash' => str_repeat('a', 64),
        'is_active' => true,
        'registered_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    try {
        $owners = new CountingOverrideOwnerResolver((string) config('core.catalog_path'), $root);
        $validator = new UiCatalogOverrideValidator($owners);
        $overrides = new UiCatalogOverrides(app(UiCatalogOverrideStore::class), $validator, new NullLogger);
        $manager = new UiCatalogOverrideManager($validator, app(UiCatalogActorResolver::class), $overrides);
        $manager->set('acme::greeting', 'es_ES', 'Hola');
        $owners->forget();
        $owners->reads = 0;

        $queries = 0;
        DB::listen(function ($query) use (&$queries): void {
            if (str_contains($query->sql, 'cms_templates')) {
                $queries++;
            }
        });

        for ($i = 0; $i < 20; $i++) {
            expect($overrides->line('acme::greeting', 'es_ES'))->toBe('Hola');
        }

        expect($queries)->toBe(1)
            ->and($owners->reads)->toBe(1);
    } finally {
        DB::table('cms_templates')->where('identifier', 'acme')->delete();
        overrideResolutionRemoveTree($root);
    }
});

test('template owner catalog changes invalidate overrides in the same and a new instance', function () {
    $root = storage_path('framework/testing/ui-override-template-owner-'.bin2hex(random_bytes(6)));
    $lang = $root.DIRECTORY_SEPARATOR.'Acme'.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'lang';
    mkdir($lang, recursive: true);
    $catalog = $lang.DIRECTORY_SEPARATOR.'en.json';

    $write = function (array $lines) use ($catalog): void {
        file_put_contents($catalog, json_encode($lines, JSON_THROW_ON_ERROR));
    };

    $write([
        'acme::greeting' => 'Hello :name',
        'acme::other' => 'Other',
    ]);

    DB::table('cms_templates')->insert([
        'directory' => 'Acme',
        'directory_key' => 'acme',
        'identifier' => 'acme',
        'name' => 'Acme',
        'manifest_hash' => str_repeat('a', 64),
        'is_active' => true,
        'registered_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    try {
        $owners = new UiCatalogOwnerResolver((string) config('core.catalog_path'), $root);
        $validator = new UiCatalogOverrideValidator($owners);
        $overrides = new UiCatalogOverrides(app(UiCatalogOverrideStore::class), $validator, new NullLogger);
        $manager = new UiCatalogOverrideManager($validator, app(UiCatalogActorResolver::class), $overrides);
        $manager->set('acme::greeting', 'es_ES', 'Hola :name');

        $resolver = new UiCatalogResolver(
            new UiCatalogRepository($lang, 'acme'),
            'key',
            new NullLogger,
            'acme',
            $overrides,
        );

        expect($resolver->get('acme::greeting', 'es_ES', ['name' => 'Ana']))->toBe('Hola Ana');

        $write(['acme::other' => 'Other']);

        expect($resolver->get('acme::greeting', 'es_ES', ['name' => 'Ana']))
            ->toBe('acme::greeting');

        $freshOverrides = new UiCatalogOverrides(app(UiCatalogOverrideStore::class), $validator, new NullLogger);
        $freshResolver = new UiCatalogResolver(
            new UiCatalogRepository($lang, 'acme'),
            'key',
            new NullLogger,
            'acme',
            $freshOverrides,
        );

        expect($freshResolver->get('acme::greeting', 'es_ES', ['name' => 'Ana']))
            ->toBe('acme::greeting');
    } finally {
        DB::table('cms_templates')->where('identifier', 'acme')->delete();
        overrideResolutionRemoveTree($root);
    }
});

test('missing override tables degrade to catalogs without a 5XX response', function () {
    $logger = Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing();
    $logger->shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $message) => $message === 'UI catalog override storage is unavailable.');

    $this->app->instance(LoggerInterface::class, $logger);

    foreach ([
        UiCatalogOverrideStore::class,
        UiCatalogOverrides::class,
        UiCatalogResolver::class,
        UiCatalogOverrideManager::class,
        TemplateUiCatalogs::class,
    ] as $service) {
        $this->app->forgetInstance($service);
    }

    Schema::dropIfExists('ui_catalog_override_state');
    Schema::dropIfExists('ui_catalog_overrides');

    try {
        $resolver = overrideResolutionCoreResolver('base');

        expect($resolver->get('core::language.installed', 'es_ES', ['locale' => 'es_ES']))
            ->toBe('Idioma es_ES instalado.')
            ->and($resolver->get('core::language.installed', 'es_ES', ['locale' => 'es_ES']))
            ->toBe('Idioma es_ES instalado.');

        $this->get('/admin/login')->assertOk()->assertSee('Administrator sign in');
        $this->get('/')->assertOk();
    } finally {
        $migration = include base_path('Modules/Core/database/migrations/2026_11_25_000000_create_ui_catalog_override_tables.php');
        $migration->up();
    }
})->skip(fn () => DB::getDriverName() !== 'sqlite', 'Destructive table checks run in the SQLite suite.');
