<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Language\Exceptions\LanguageOperationException;
use Modules\Core\Language\Models\Language;
use Modules\Core\Language\Services\LanguageManager;
use Modules\Core\Localization\Exceptions\CatalogValidationException;
use Modules\Core\Localization\Services\UiCatalogRepository;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->languageTestRoot = storage_path('framework/testing/languages-'.bin2hex(random_bytes(6)));
    $this->catalogPath = $this->languageTestRoot.DIRECTORY_SEPARATOR.'catalogs';
    mkdir($this->catalogPath, recursive: true);

    file_put_contents($this->catalogPath.DIRECTORY_SEPARATOR.'en.json', json_encode([
        'core::language.installed' => 'Language :locale installed.',
    ], JSON_THROW_ON_ERROR));

    $this->manager = new LanguageManager(new UiCatalogRepository($this->catalogPath));
});

afterEach(function () {
    foreach (glob($this->languageTestRoot.DIRECTORY_SEPARATOR.'**'.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
        if (is_file($file) || is_link($file)) {
            unlink($file);
        }
    }

    foreach (array_reverse(glob($this->languageTestRoot.DIRECTORY_SEPARATOR.'*') ?: []) as $path) {
        is_dir($path) ? rmdir($path) : unlink($path);
    }

    is_dir($this->languageTestRoot) ? rmdir($this->languageTestRoot) : null;
});

test('a valid manifest installs an inactive language atomically', function () {
    $manifest = languageManifest($this->languageTestRoot, $this->catalogPath);

    $language = $this->manager->install($manifest);

    expect($language->locale)->toBe('es_ES')
        ->and($language->is_active)->toBeFalse()
        ->and(fn () => $this->manager->install($manifest))
        ->toThrow(LanguageOperationException::class)
        ->and(Language::where('locale', 'es_ES')->count())->toBe(1);
});

test('activation defaults and disabling preserve all language invariants', function () {
    $manifest = languageManifest($this->languageTestRoot, $this->catalogPath);
    $this->manager->install($manifest);

    $this->manager->activate('es_ES');
    $this->manager->setDefault('frontend', 'es_ES');
    $this->manager->setDefault('backoffice', 'es_ES');

    expect(fn () => $this->manager->disable('es_ES'))
        ->toThrow(LanguageOperationException::class);

    $this->manager->disable('en');

    expect(Language::where('locale', 'en')->value('is_active'))->toBeFalse()
        ->and(fn () => $this->manager->setDefault('frontend', 'en'))
        ->toThrow(LanguageOperationException::class);
});

test('invalid manifests and catalogs do not create partial language records', function () {
    $manifest = languageManifest($this->languageTestRoot, $this->catalogPath);
    file_put_contents($this->catalogPath.DIRECTORY_SEPARATOR.'es_ES.json', '{}');

    expect(fn () => $this->manager->install($manifest))
        ->toThrow(CatalogValidationException::class)
        ->and(Language::where('locale', 'es_ES')->exists())->toBeFalse();
});

test('manifest schema types and size limits fail before persistence', function () {
    $manifest = languageManifest($this->languageTestRoot, $this->catalogPath);
    $invalid = json_decode(file_get_contents($manifest), true, flags: JSON_THROW_ON_ERROR);
    $invalid['locale'] = 123;
    file_put_contents($manifest, json_encode($invalid, JSON_THROW_ON_ERROR));

    expect(fn () => $this->manager->install($manifest))
        ->toThrow(LanguageOperationException::class);

    file_put_contents($manifest, str_repeat('x', 65_537));

    expect(fn () => $this->manager->install($manifest))
        ->toThrow(LanguageOperationException::class)
        ->and(Language::where('locale', 'es_ES')->exists())->toBeFalse();
});

test('manifest display fields reject terminal control characters', function () {
    $manifest = languageManifest($this->languageTestRoot, $this->catalogPath);
    $invalid = json_decode(file_get_contents($manifest), true, flags: JSON_THROW_ON_ERROR);
    $invalid['native_name'] = "Español\e[31m";
    file_put_contents($manifest, json_encode($invalid, JSON_THROW_ON_ERROR));

    expect(fn () => $this->manager->install($manifest))
        ->toThrow(LanguageOperationException::class)
        ->and(Language::where('locale', 'es_ES')->exists())->toBeFalse();
});

function languageManifest(string $root, string $catalogPath): string
{
    file_put_contents($catalogPath.DIRECTORY_SEPARATOR.'es_ES.json', json_encode([
        'core::language.installed' => 'Idioma :locale instalado.',
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

    $manifest = $root.DIRECTORY_SEPARATOR.'es_ES-manifest.json';
    file_put_contents($manifest, json_encode([
        'schema_version' => 1,
        'locale' => 'es_ES',
        'name' => 'Spanish (Spain)',
        'native_name' => 'Español (España)',
        'text_direction' => 'ltr',
        'catalogs' => ['core' => true],
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

    return $manifest;
}
