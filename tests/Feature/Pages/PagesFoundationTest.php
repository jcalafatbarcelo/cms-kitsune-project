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

test('page commands create deterministic draft pages after the initial home', function () {
    $this->artisan('cms:page:create', ['locale' => 'en', 'slug' => 'about', 'title' => 'About'])
        ->expectsOutputToContain('created')
        ->assertSuccessful();

    expect(PageTranslation::query()->where('slug', 'about')->sole()->is_published)->toBeFalse();
});
