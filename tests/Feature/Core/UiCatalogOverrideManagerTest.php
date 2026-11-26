<?php

use App\Models\User;
use App\Services\Admin\SuperAdminBootstrap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Core\Localization\Exceptions\CatalogValidationException;
use Modules\Core\Localization\Exceptions\UiCatalogOverrideException;
use Modules\Core\Localization\Models\UiCatalogOverride;
use Modules\Core\Localization\Models\UiCatalogOverrideState;
use Modules\Core\Localization\Services\UiCatalogActorResolver;
use Modules\Core\Localization\Services\UiCatalogOverrideManager;
use Modules\Core\Localization\Services\UiCatalogOverrides;
use Modules\Core\Localization\Services\UiCatalogOverrideStore;
use Modules\Core\Localization\Services\UiCatalogOverrideValidator;
use Modules\Core\Localization\Services\UiCatalogOwnerResolver;

uses(RefreshDatabase::class);

function overrideManagerInstallLanguage(string $locale = 'es_ES', string $prefix = 'es-es'): void
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

function overrideManagerBindCoreCatalog(string $path): void
{
    $owners = new UiCatalogOwnerResolver($path, base_path('Templates'));
    $validator = new UiCatalogOverrideValidator($owners);
    $overrides = new UiCatalogOverrides(
        app(UiCatalogOverrideStore::class),
        $validator,
        app('log'),
    );

    app()->instance(UiCatalogOwnerResolver::class, $owners);
    app()->instance(UiCatalogOverrideValidator::class, $validator);
    app()->instance(UiCatalogOverrides::class, $overrides);
    app()->instance(UiCatalogOverrideManager::class, new UiCatalogOverrideManager(
        $validator,
        app(UiCatalogActorResolver::class),
        $overrides,
    ));
}

function overrideManagerRemoveTree(string $path): void
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
    overrideManagerInstallLanguage();
});

test('a valid override is created and updated while preserving the creator and incrementing the version', function () {
    $manager = app(UiCatalogOverrideManager::class);

    $created = $manager->set('core::language.installed', 'es_ES', 'Idioma :locale instalado.');
    $createdAt = $created['override']->created_at;

    expect($created['created'])->toBeTrue()
        ->and($created['override']->created_by)->toBe('console')
        ->and($created['override']->updated_by)->toBe('console')
        ->and($createdAt)->not->toBeNull()
        ->and(UiCatalogOverrideState::query()->find(1)->version)->toBe(2);

    $user = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $this->actingAs($user, 'web');

    $updated = $manager->set('core::language.installed', 'es_ES', 'Idioma alternativo :locale instalado.');

    expect($updated['created'])->toBeFalse()
        ->and($updated['override']->created_by)->toBe('console')
        ->and($updated['override']->created_at->equalTo($createdAt))->toBeTrue()
        ->and($updated['override']->updated_by)->toBe('user:'.$user->id)
        ->and($updated['override']->value)->toBe('Idioma alternativo :locale instalado.')
        ->and(UiCatalogOverride::query()->count())->toBe(1)
        ->and(UiCatalogOverrideState::query()->find(1)->version)->toBe(3);
});

test('a mutation without a human actor records the system snapshot', function () {
    $manager = new UiCatalogOverrideManager(
        app(UiCatalogOverrideValidator::class),
        new UiCatalogActorResolver(app('auth'), false),
        app(UiCatalogOverrides::class),
    );

    $result = $manager->set('core::language.installed', 'es_ES', 'Idioma :locale instalado.');

    expect($result['override']->created_by)->toBe('system')
        ->and($result['override']->updated_by)->toBe('system');
});

test('an authenticated web user without superadministrator access cannot mutate overrides', function () {
    $this->actingAs(User::factory()->create(), 'web');

    expect(fn () => app(UiCatalogOverrideManager::class)->set(
        'core::language.installed',
        'es_ES',
        'Idioma :locale instalado.',
    ))->toThrow(UiCatalogOverrideException::class, 'superadministrator')
        ->and(UiCatalogOverride::query()->count())->toBe(0)
        ->and(UiCatalogOverrideState::query()->find(1)->version)->toBe(1);
});

test('an authenticated web user without superadministrator access cannot remove overrides', function () {
    $manager = app(UiCatalogOverrideManager::class);
    $manager->set('core::language.installed', 'es_ES', 'Idioma :locale instalado.');

    $this->actingAs(User::factory()->create(), 'web');

    expect(fn () => $manager->remove('core::language.installed', 'es_ES'))
        ->toThrow(UiCatalogOverrideException::class, 'superadministrator')
        ->and(UiCatalogOverride::query()->count())->toBe(1)
        ->and(UiCatalogOverrideState::query()->find(1)->version)->toBe(2);
});

test('an authenticated web user without superadministrator access cannot toggle overrides', function () {
    $manager = app(UiCatalogOverrideManager::class);
    $manager->set('core::language.installed', 'es_ES', 'Idioma :locale instalado.');

    $this->actingAs(User::factory()->create(), 'web');

    expect(fn () => $manager->toggle(false))
        ->toThrow(UiCatalogOverrideException::class, 'superadministrator')
        ->and(UiCatalogOverrideState::query()->find(1)->enabled)->toBeTrue()
        ->and(UiCatalogOverrideState::query()->find(1)->version)->toBe(2)
        ->and(UiCatalogOverride::query()->count())->toBe(1);
});

test('a no-op toggle still requires superadministrator authorization', function () {
    $manager = app(UiCatalogOverrideManager::class);
    $manager->toggle(false);

    $this->actingAs(User::factory()->create(), 'web');

    expect(fn () => $manager->toggle(false))
        ->toThrow(UiCatalogOverrideException::class, 'superadministrator')
        ->and(UiCatalogOverrideState::query()->find(1)->enabled)->toBeFalse()
        ->and(UiCatalogOverrideState::query()->find(1)->version)->toBe(2)
        ->and(UiCatalogOverrideState::query()->find(1)->updated_by)->toBe('console');
});

test('authorization precedes validation for set', function () {
    $this->actingAs(User::factory()->create(), 'web');

    expect(fn () => app(UiCatalogOverrideManager::class)->set(
        'Core::language.installed',
        'fr_FR',
        '<strong>unsafe</strong>',
    ))->toThrow(UiCatalogOverrideException::class, 'superadministrator')
        ->and(UiCatalogOverride::query()->count())->toBe(0)
        ->and(UiCatalogOverrideState::query()->find(1)->version)->toBe(1);
});

test('invalid locales owners keys values and compatibility are rejected without writing', function () {
    $manager = app(UiCatalogOverrideManager::class);

    expect(fn () => $manager->set('core::language.installed', 'fr_FR', 'Idioma :locale instalado.'))
        ->toThrow(UiCatalogOverrideException::class, 'not installed')
        ->and(fn () => $manager->set('core::language.installed', '../en', 'Idioma'))
        ->toThrow(CatalogValidationException::class)
        ->and(fn () => $manager->set('Core::language.installed', 'es_ES', 'Idioma'))
        ->toThrow(UiCatalogOverrideException::class, 'key is invalid')
        ->and(fn () => $manager->set('unknown::language.installed', 'es_ES', 'Idioma'))
        ->toThrow(UiCatalogOverrideException::class, 'not resolvable')
        ->and(fn () => $manager->set('core::missing.key', 'es_ES', 'Idioma'))
        ->toThrow(UiCatalogOverrideException::class, 'does not exist')
        ->and(fn () => $manager->set('core::language.installed', 'es_ES', ''))
        ->toThrow(UiCatalogOverrideException::class, 'value')
        ->and(fn () => $manager->set('core::language.installed', 'es_ES', '<strong>Idioma</strong>'))
        ->toThrow(UiCatalogOverrideException::class, 'value')
        ->and(fn () => $manager->set('core::language.installed', 'es_ES', "Idioma\x07 :locale"))
        ->toThrow(UiCatalogOverrideException::class, 'value')
        ->and(fn () => $manager->set('core::language.installed', 'es_ES', 'Idioma instalado.'))
        ->toThrow(UiCatalogOverrideException::class, 'placeholders differ')
        ->and(fn () => $manager->set('core::language.installed', 'es_ES', str_repeat('a', 16_385)))
        ->toThrow(UiCatalogOverrideException::class, 'value');

    expect(UiCatalogOverride::query()->count())->toBe(0)
        ->and(UiCatalogOverrideState::query()->find(1)->version)->toBe(1);
});

test('unicode control and bidi characters are rejected consistently', function () {
    $manager = app(UiCatalogOverrideManager::class);

    expect(fn () => $manager->set('core::language.installed', 'es_ES', "Idioma\u{0085} :locale instalado."))
        ->toThrow(UiCatalogOverrideException::class, 'value')
        ->and(fn () => $manager->set('core::language.installed', 'es_ES', "Idioma\u{202E} :locale instalado."))
        ->toThrow(UiCatalogOverrideException::class, 'value')
        ->and(UiCatalogOverride::query()->count())->toBe(0)
        ->and(UiCatalogOverrideState::query()->find(1)->version)->toBe(1);
});

test('legitimate zero-width joiner and RTL marks are accepted in overrides', function () {
    $manager = app(UiCatalogOverrideManager::class);

    $manager->set('core::language.installed', 'es_ES', "Idioma\u{200D} :locale instalado.");
    $manager->set('core::language.installed', 'es_ES', "Idioma\u{200F} :locale instalado.");

    expect(UiCatalogOverride::query()->count())->toBe(1)
        ->and(UiCatalogOverrideState::query()->find(1)->version)->toBe(3)
        ->and(UiCatalogOverride::query()->sole()->value)->toBe("Idioma\u{200F} :locale instalado.");
});

test('plural overrides require valid segments and a matching base pluralization', function () {
    $directory = storage_path('framework/testing/ui-override-plural-'.bin2hex(random_bytes(6)));
    mkdir($directory, recursive: true);
    file_put_contents($directory.DIRECTORY_SEPARATOR.'en.json', json_encode([
        'core::greeting' => 'Hello :name',
        'core::items.count' => '{0} No items|{1} One item|[2,*] :count items',
    ], JSON_THROW_ON_ERROR));

    try {
        overrideManagerBindCoreCatalog($directory);
        $manager = app(UiCatalogOverrideManager::class);

        $manager->set('core::items.count', 'es_ES', '{0} Sin elementos|{1} Un elemento|[2,*] :count elementos');

        expect(UiCatalogOverride::query()->count())->toBe(1)
            ->and(fn () => $manager->set('core::items.count', 'es_ES', '{1} Uno|'))
            ->toThrow(UiCatalogOverrideException::class)
            ->and(fn () => $manager->set('core::items.count', 'es_ES', '{1} Uno|[2,*] Muchos'))
            ->toThrow(UiCatalogOverrideException::class, 'value')
            ->and(fn () => $manager->set('core::items.count', 'es_ES', 'Elementos :count'))
            ->toThrow(UiCatalogOverrideException::class, 'pluralization differs')
            ->and(UiCatalogOverride::query()->count())->toBe(1);
    } finally {
        overrideManagerRemoveTree($directory);
    }
});

test('the global switch ignores overrides without deleting rows and preserves the actor snapshot', function () {
    $manager = app(UiCatalogOverrideManager::class);
    $manager->set('core::language.installed', 'es_ES', 'Idioma :locale instalado.');

    $user = app(SuperAdminBootstrap::class)->create('Administrator', 'admin@example.test', 'Safe-Test-Password9!');
    $this->actingAs($user, 'web');

    $disabled = $manager->toggle(false);

    expect($disabled)->toMatchArray(['changed' => true, 'enabled' => false, 'version' => 3])
        ->and(UiCatalogOverride::query()->count())->toBe(1)
        ->and(UiCatalogOverrideState::query()->find(1)->updated_by)->toBe('user:'.$user->id);

    $again = $manager->toggle(false);

    expect($again)->toMatchArray(['changed' => false, 'enabled' => false, 'version' => 3]);

    $enabled = $manager->toggle(true);

    expect($enabled)->toMatchArray(['changed' => true, 'enabled' => true, 'version' => 4])
        ->and(UiCatalogOverride::query()->sole()->value)->toBe('Idioma :locale instalado.');
});

test('remove can delete exact malformed keys without relaxing set', function () {
    $key = 'Core::language.installed';
    $now = now();

    DB::table('ui_catalog_overrides')->insert([
        'locale' => 'es_ES',
        'key' => $key,
        'value' => 'Idioma :locale instalado.',
        'created_by' => 'system',
        'updated_by' => 'system',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $manager = app(UiCatalogOverrideManager::class);

    expect(fn () => $manager->set($key, 'es_ES', 'Idioma :locale instalado.'))
        ->toThrow(UiCatalogOverrideException::class, 'key is invalid')
        ->and(UiCatalogOverrideState::query()->find(1)->version)->toBe(1);

    $manager->remove($key, 'es_ES');

    expect(UiCatalogOverride::query()->count())->toBe(0)
        ->and(UiCatalogOverrideState::query()->find(1)->version)->toBe(2);
});

test('removing an existing override deletes the row and missing rows fail', function () {
    $manager = app(UiCatalogOverrideManager::class);
    $manager->set('core::language.installed', 'es_ES', 'Idioma :locale instalado.');

    $manager->remove('core::language.installed', 'es_ES');

    expect(UiCatalogOverride::query()->count())->toBe(0)
        ->and(UiCatalogOverrideState::query()->find(1)->version)->toBe(3);

    expect(fn () => $manager->remove('core::language.installed', 'es_ES'))
        ->toThrow(UiCatalogOverrideException::class, 'does not exist')
        ->and(UiCatalogOverrideState::query()->find(1)->version)->toBe(3);
});
