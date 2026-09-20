<?php

use Modules\Core\Localization\Exceptions\CatalogValidationException;
use Modules\Core\Localization\Services\UiCatalogOwnerRegistry;
use Modules\Core\Localization\Services\UiCatalogRepository;
use Modules\Core\Localization\Services\UiCatalogResolver;
use Psr\Log\NullLogger;

function catalogDirectory(): string
{
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kitsune-catalog-'.bin2hex(random_bytes(6));
    mkdir($path, recursive: true);

    return $path;
}

function writeCatalog(string $directory, string $locale, array $lines): void
{
    file_put_contents(
        $directory.DIRECTORY_SEPARATOR.$locale.'.json',
        json_encode($lines, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
    );
}

function removeCatalogDirectory(string $directory): void
{
    foreach (glob($directory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
        is_link($file) ? unlink($file) : unlink($file);
    }

    rmdir($directory);
}

test('valid catalogs resolve requested lines and configured fallback modes', function () {
    $directory = catalogDirectory();

    try {
        writeCatalog($directory, 'en', [
            'core::auth.login' => 'Log in',
            'core::items.count' => '{0} No items|{1} One item|[2,*] :count items',
        ]);
        writeCatalog($directory, 'es_ES', [
            'core::auth.login' => 'Acceder',
            'core::items.count' => '{0} Sin elementos|{1} Un elemento|[2,*] :count elementos',
        ]);

        $repository = new UiCatalogRepository($directory);

        expect((new UiCatalogResolver($repository, 'base', new NullLogger))->get('core::auth.login', 'es_ES'))
            ->toBe('Acceder')
            ->and((new UiCatalogResolver($repository, 'base', new NullLogger))->get('core::auth.login', 'fr_FR'))
            ->toBe('Log in')
            ->and((new UiCatalogResolver($repository, 'key', new NullLogger))->get('core::auth.login', 'fr_FR'))
            ->toBe('core::auth.login')
            ->and((new UiCatalogResolver($repository, 'base', new NullLogger))->choice('core::items.count', 3, 'es_ES'))
            ->toBe('3 elementos');
    } finally {
        removeCatalogDirectory($directory);
    }
});

test('plural selection uses the primary language for accepted regional locales', function () {
    $directory = catalogDirectory();

    try {
        writeCatalog($directory, 'en', ['core::items.count' => 'One item|:count items']);
        writeCatalog($directory, 'es_419', ['core::items.count' => 'Un elemento|:count elementos']);

        expect((new UiCatalogResolver(
            new UiCatalogRepository($directory),
            'base',
            new NullLogger,
        ))->choice('core::items.count', 3, 'es_419'))->toBe('3 elementos');
    } finally {
        removeCatalogDirectory($directory);
    }
});

test('catalog validation rejects incomplete unknown and incompatible lines', function (array $localized) {
    $directory = catalogDirectory();

    try {
        writeCatalog($directory, 'en', [
            'core::greeting' => 'Hello :name',
            'core::items' => '{1} One :count|[2,*] Many :count',
        ]);
        writeCatalog($directory, 'es_ES', $localized);

        expect(fn () => (new UiCatalogRepository($directory))->snapshot('es_ES'))
            ->toThrow(CatalogValidationException::class);
    } finally {
        removeCatalogDirectory($directory);
    }
})->with([
    'missing key' => [[
        'core::greeting' => 'Hola :name',
    ]],
    'unknown key' => [[
        'core::greeting' => 'Hola :name',
        'core::items' => '{1} Uno :count|[2,*] Muchos :count',
        'core::unknown' => 'Desconocido',
    ]],
    'placeholder mismatch' => [[
        'core::greeting' => 'Hola :person',
        'core::items' => '{1} Uno :count|[2,*] Muchos :count',
    ]],
    'pluralization mismatch' => [[
        'core::greeting' => 'Hola :name',
        'core::items' => 'Elementos :count',
    ]],
]);

test('hash based validation observes catalog changes after an earlier valid read', function () {
    $directory = catalogDirectory();

    try {
        writeCatalog($directory, 'en', ['core::status.ready' => 'Ready']);
        writeCatalog($directory, 'es_ES', ['core::status.ready' => 'Preparado']);

        $repository = new UiCatalogRepository($directory);
        $resolver = new UiCatalogResolver($repository, 'base', new NullLogger);

        expect($resolver->get('core::status.ready', 'es_ES'))->toBe('Preparado');

        writeCatalog($directory, 'es_ES', ['core::status.other' => 'Otro']);

        expect($resolver->get('core::status.ready', 'es_ES'))->toBe('Ready');
    } finally {
        removeCatalogDirectory($directory);
    }
});

test('owner registry rejects normalized collisions and reserves core', function () {
    $registry = new UiCatalogOwnerRegistry;

    $registry->register('Core', reserved: true);
    $registry->register('Blog Posts');

    expect(fn () => $registry->register('blog-posts'))
        ->toThrow(LogicException::class)
        ->and(fn () => $registry->register('core'))
        ->toThrow(LogicException::class);
});

test('resolver rejects an unknown fallback mode', function () {
    $directory = catalogDirectory();

    try {
        expect(fn () => new UiCatalogResolver(
            new UiCatalogRepository($directory),
            'unknown',
            new NullLogger,
        ))->toThrow(InvalidArgumentException::class);
    } finally {
        removeCatalogDirectory($directory);
    }
});

test('resolver rejects invalid identifiers before querying or logging them', function () {
    $directory = catalogDirectory();
    $resolver = new UiCatalogResolver(new UiCatalogRepository($directory), 'base', new NullLogger);

    try {
        expect(fn () => $resolver->get("core::invalid\e[31m", 'en'))
            ->toThrow(CatalogValidationException::class)
            ->and(fn () => $resolver->get('core::valid', "en\e[31m"))
            ->toThrow(CatalogValidationException::class);
    } finally {
        removeCatalogDirectory($directory);
    }
});

test('catalog validation rejects html oversized files invalid utf8 and unsafe locales', function () {
    $directory = catalogDirectory();

    try {
        writeCatalog($directory, 'en', ['core::unsafe.html' => '<strong>Unsafe</strong>']);
        expect(fn () => (new UiCatalogRepository($directory))->snapshot('en'))
            ->toThrow(CatalogValidationException::class);

        file_put_contents($directory.DIRECTORY_SEPARATOR.'en.json', str_repeat('x', 2 * 1024 * 1024 + 1));
        expect(fn () => (new UiCatalogRepository($directory))->snapshot('en'))
            ->toThrow(CatalogValidationException::class);

        file_put_contents($directory.DIRECTORY_SEPARATOR.'en.json', "{\"core::invalid.utf8\":\"\xB1\"}");
        expect(fn () => (new UiCatalogRepository($directory))->snapshot('en'))
            ->toThrow(CatalogValidationException::class)
            ->and(fn () => (new UiCatalogRepository($directory))->snapshot('../en'))
            ->toThrow(CatalogValidationException::class);
    } finally {
        removeCatalogDirectory($directory);
    }
});

test('catalog errors do not echo control characters from invalid keys', function () {
    $directory = catalogDirectory();

    try {
        writeCatalog($directory, 'en', ["core::invalid\e[31m" => 'Unsafe']);

        try {
            (new UiCatalogRepository($directory))->snapshot('en');
            test()->fail('The invalid key was accepted.');
        } catch (CatalogValidationException $exception) {
            expect($exception->getMessage())->not->toContain("\e");
        }
    } finally {
        removeCatalogDirectory($directory);
    }
});
