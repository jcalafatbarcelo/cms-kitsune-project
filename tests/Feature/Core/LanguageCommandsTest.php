<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Language\Services\LanguageManager;
use Modules\Core\Localization\Services\UiCatalogRepository;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->languageCommandRoot = storage_path('framework/testing/commands-'.bin2hex(random_bytes(6)));
    $catalogPath = $this->languageCommandRoot.DIRECTORY_SEPARATOR.'catalogs';
    mkdir($catalogPath, recursive: true);

    file_put_contents($catalogPath.DIRECTORY_SEPARATOR.'en.json', json_encode([
        'core::language.installed' => 'Language :locale installed.',
    ], JSON_THROW_ON_ERROR));
    file_put_contents($catalogPath.DIRECTORY_SEPARATOR.'es_ES.json', json_encode([
        'core::language.installed' => 'Idioma :locale instalado.',
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

    $manifest = $this->languageCommandRoot.DIRECTORY_SEPARATOR.'manifest.json';
    file_put_contents($manifest, json_encode([
        'schema_version' => 1,
        'locale' => 'es_ES',
        'name' => 'Spanish (Spain)',
        'native_name' => 'Español (España)',
        'text_direction' => 'ltr',
        'catalogs' => ['core' => true],
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

    $this->manifest = $manifest;
    $this->app->instance(
        LanguageManager::class,
        new LanguageManager(new UiCatalogRepository($catalogPath)),
    );
});

afterEach(function () {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->languageCommandRoot, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $entry) {
        $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }

    rmdir($this->languageCommandRoot);
});

test('language commands expose deterministic successful operations', function () {
    $this->artisan('cms:language:list')
        ->expectsTable(['Locale', 'Name', 'Native name', 'Direction', 'Active'], [
            ['en', 'English', 'English', 'ltr', 'yes'],
        ])
        ->assertSuccessful();

    $this->artisan('cms:language:install', ['manifest' => $this->manifest])
        ->expectsOutputToContain('es_ES')
        ->assertSuccessful();
    $this->artisan('cms:language:activate', ['locale' => 'es_ES'])
        ->expectsOutputToContain('es_ES')
        ->assertSuccessful();
    $this->artisan('cms:language:set-default', ['context' => 'frontend', 'locale' => 'es_ES'])
        ->expectsOutputToContain('frontend')
        ->assertSuccessful();
    $this->artisan('cms:language:validate', ['locale' => 'es_ES'])
        ->expectsOutputToContain('es_ES')
        ->doesntExpectOutputToContain('Idioma :locale instalado.')
        ->assertSuccessful();
});

test('language commands return failure for invalid and prohibited operations', function () {
    $this->artisan('cms:language:install', ['manifest' => $this->manifest])->assertSuccessful();

    $this->artisan('cms:language:install', ['manifest' => $this->manifest])
        ->expectsOutputToContain('already installed')
        ->assertFailed();
    $this->artisan('cms:language:disable', ['locale' => 'en'])
        ->expectsOutputToContain('cannot be disabled')
        ->assertFailed();
    $this->artisan('cms:language:set-default', ['context' => 'invalid', 'locale' => 'en'])
        ->expectsOutputToContain('Context')
        ->assertFailed();
    $this->artisan('cms:language:validate', ['locale' => 'fr_FR'])
        ->expectsOutputToContain('not installed')
        ->assertFailed();
});
