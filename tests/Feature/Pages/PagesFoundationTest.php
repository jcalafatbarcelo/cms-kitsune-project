<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Pages\Exceptions\PageOperationException;
use Modules\Pages\Models\PageLanguageHome;
use Modules\Pages\Models\PageTranslation;
use Modules\Pages\Services\PageManager;

uses(RefreshDatabase::class);

test('a clean installation provides the published English construction home', function () {
    $home = PageLanguageHome::query()->sole();
    $translation = PageTranslation::query()->with('page')->findOrFail($home->page_translation_id);

    expect($translation->title)->toBe('Under construction')
        ->and($translation->is_published)->toBeTrue()
        ->and($translation->page->is_published)->toBeTrue();

    $this->get('/')
        ->assertOk()
        ->assertSee('Under construction')
        ->assertSee('This site is being prepared.');
});

test('the first page translation for an installed language becomes its published home', function () {
    $now = now();
    DB::table('languages')->insert([
        'locale' => 'es_ES',
        'name' => 'Spanish',
        'native_name' => 'Español',
        'text_direction' => 'ltr',
        'is_active' => true,
        'installed_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $translation = app(PageManager::class)->create('es_ES', 'inicio', 'En construcción');

    expect($translation->is_published)->toBeTrue()
        ->and($translation->page->fresh()->is_published)->toBeTrue()
        ->and(PageLanguageHome::query()->where('page_translation_id', $translation->id)->exists())->toBeTrue();
});

test('a home page cannot be unpublished without a replacement', function () {
    $home = PageLanguageHome::query()->sole();
    $translation = PageTranslation::query()->findOrFail($home->page_translation_id);

    expect(fn () => app(PageManager::class)->unpublish($translation->page_id, 'en'))
        ->toThrow(PageOperationException::class);
});

test('an ancestor of the home page cannot be unpublished without a replacement', function () {
    $pages = app(PageManager::class);
    $root = PageTranslation::query()->sole();
    $child = $pages->create('en', 'child', 'Child', $root->page_id);
    $pages->publish($child->page_id, 'en');
    $pages->setHome($child->page_id, 'en');

    expect(fn () => $pages->unpublish($root->page_id, 'en'))
        ->toThrow(PageOperationException::class)
        ->and($root->fresh()->is_published)->toBeTrue();
});

test('page creation and translation reject invalid parent and duplicate records with domain errors', function () {
    $pages = app(PageManager::class);
    $home = PageTranslation::query()->sole();
    $pages->create('en', 'about', 'About');

    expect(fn () => $pages->create('en', 'child', 'Child', 999))
        ->toThrow(PageOperationException::class, 'Parent page [999] is not available.')
        ->and(fn () => $pages->create('en', 'about', 'Another about'))
        ->toThrow(PageOperationException::class, 'Slug [about] is already in use for this language.')
        ->and(fn () => $pages->translate($home->page_id, 'en', 'another-home', 'Another home'))
        ->toThrow(PageOperationException::class, "Page [{$home->page_id}] already has a translation for [en].");
});

test('page commands create deterministic draft pages after the initial home', function () {
    $this->artisan('cms:page:create', ['locale' => 'en', 'slug' => 'about', 'title' => 'About'])
        ->expectsOutputToContain('created')
        ->assertSuccessful();

    expect(PageTranslation::query()->where('slug', 'about')->sole()->is_published)->toBeFalse();
});

test('page commands return a controlled failure for domain and catalog errors', function () {
    $this->artisan('cms:page:create', ['locale' => 'en', 'slug' => 'child', 'title' => 'Child', '--parent' => 999])
        ->expectsOutputToContain('Parent page [999] is not available.')
        ->assertFailed();

    $catalog = base_path('Templates/Base/Resources/lang/en.json');
    $missingCatalog = $catalog.'.missing';
    rename($catalog, $missingCatalog);

    try {
        $this->artisan('cms:page:create', ['locale' => 'en', 'slug' => 'catalog-error', 'title' => 'Catalog error'])
            ->expectsOutputToContain('UI catalog [en] is not a regular file.')
            ->assertFailed();
    } finally {
        rename($missingCatalog, $catalog);
    }
});

test('the public route returns service unavailable when its template catalog is invalid', function () {
    $catalog = base_path('Templates/Base/Resources/lang/en.json');
    $missingCatalog = $catalog.'.missing';
    rename($catalog, $missingCatalog);

    try {
        $this->get('/')->assertServiceUnavailable();
    } finally {
        rename($missingCatalog, $catalog);
    }
});
