<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\Core\Language\Exceptions\LanguageOperationException;
use Modules\Core\Language\Services\LanguageManager;
use Modules\Pages\Exceptions\PageOperationException;
use Modules\Pages\Services\PageManager;
use Modules\Pages\Services\PublicPageResolver;

uses(RefreshDatabase::class);

function addPublicLanguage(string $locale, string $prefix, bool $general = false): void
{
    $now = now();
    DB::table('languages')->insert([
        'locale' => $locale,
        'url_prefix' => $prefix,
        'name' => $locale,
        'native_name' => $locale,
        'text_direction' => 'ltr',
        'is_active' => true,
        'is_url_general' => $general,
        'installed_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

function localizedRequest(string $uri): Request
{
    $request = Request::create($uri, 'GET', [], [], [], ['REQUEST_URI' => $uri]);
    $request->setLaravelSession(app('session.store'));

    return $request;
}

test('localized aliases resolve hierarchical public pages and regional aliases redirect canonically', function () {
    addPublicLanguage('es_ES', 'es-es');
    $pages = app(PageManager::class);
    $root = $pages->create('es_ES', 'inicio', 'Inicio');
    $child = $pages->create('es_ES', 'hijo', 'Hijo', $root->page_id);
    $pages->publish($child->page_id, 'es_ES');

    $this->get('/es/inicio/hijo')->assertOk()->assertSee('Hijo');
    $this->get('/es-es/inicio/hijo')->assertRedirect('/es/inicio/hijo')->assertHeader('Cache-Control', 'no-store, private');
    $this->get('/inicio/hijo')->assertNotFound();
    $this->get('/es/inicio/missing')->assertNotFound();
});

test('URL selection, session selection, and initial browser negotiation use canonical temporary redirects', function () {
    addPublicLanguage('es_ES', 'es-es');
    app(PageManager::class)->create('es_ES', 'inicio', 'Inicio');

    $this->get('/es')->assertRedirect('/es/')->assertStatus(301);
    expect(app(PublicPageResolver::class)->handle(localizedRequest('/es/'), 'es'))->toBeInstanceOf(View::class);
    $this->withSession(['public_locale' => 'es_ES'])->get('/')->assertRedirect('/es/')->assertHeader('Cache-Control', 'no-store, private');
    $this->withHeaders(['Accept-Language' => 'es-ES, en;q=0.4'])->get('/')->assertRedirect('/es/')->assertSessionHas('public_locale', 'es_ES');
    $this->withSession(['public_locale' => 'es_ES'])->get('/home')->assertOk()->assertSessionHas('public_locale', 'en');
});

test('a general language is required before activating a second variant and aliases can resolve the frontend default', function () {
    addPublicLanguage('es_ES', 'es-es');
    addPublicLanguage('es_MX', 'es-mx', false);
    DB::table('languages')->where('locale', 'es_MX')->update(['is_active' => false]);

    expect(fn () => app(LanguageManager::class)->activate('es_MX'))
        ->toThrow(LanguageOperationException::class);
    app(LanguageManager::class)->setUrlGeneral('es_ES');
    app(LanguageManager::class)->activate('es_MX');

    DB::table('language_settings')->where('id', 1)->update([
        'frontend_default_language_id' => DB::table('languages')->where('locale', 'es_ES')->value('id'),
    ]);
    $this->get('/es')->assertRedirect('/')->assertStatus(302)->assertHeader('Cache-Control', 'no-store, private');
    $this->get('/es-es')->assertRedirect('/')->assertStatus(302)->assertHeader('Cache-Control', 'no-store, private');
});

test('only localized homes retain a trailing slash', function () {
    addPublicLanguage('es_ES', 'es-es');
    $pages = app(PageManager::class);
    $root = $pages->create('es_ES', 'inicio', 'Inicio');
    $pages->create('en', 'about', 'About');

    $this->get('/es-es')->assertRedirect('/es/')->assertStatus(302);
    expect(app(PublicPageResolver::class)->handle(localizedRequest('/es/inicio/'), 'es/inicio')->getStatusCode())->toBe(301);
    expect(app(PublicPageResolver::class)->handle(localizedRequest('/about/'), 'about')->getStatusCode())->toBe(301);
    expect(app(PublicPageResolver::class)->handle(localizedRequest('/es/'), 'es'))->toBeInstanceOf(View::class);
    expect($root->slug)->toBe('inicio');
});

test('root prefix-shaped slugs are reserved while nested slugs remain valid', function () {
    $pages = app(PageManager::class);
    expect(fn () => $pages->create('en', 'es-es', 'Reserved'))
        ->toThrow(PageOperationException::class);
    $root = $pages->create('en', 'parent', 'Parent');
    expect($pages->create('en', 'es-es', 'Nested', $root->page_id)->slug)->toBe('es-es');
});

test('the configured health endpoint bypasses the Pages catch-all', function () {
    $this->getJson('/up')
        ->assertOk()
        ->assertJsonPath('status', 'up');
});

test('a short prefix cannot activate beside a regional URL general language', function () {
    addPublicLanguage('es_ES', 'es-es', true);
    $now = now();
    DB::table('languages')->insert([
        'locale' => 'es',
        'url_prefix' => 'es',
        'name' => 'Spanish',
        'native_name' => 'Spanish',
        'text_direction' => 'ltr',
        'is_active' => false,
        'is_url_general' => false,
        'installed_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    expect(fn () => app(LanguageManager::class)->activate('es'))
        ->toThrow(LanguageOperationException::class)
        ->and((bool) DB::table('languages')->where('locale', 'es')->value('is_active'))->toBeFalse();
});

test('a regional variant cannot replace an active short URL general language', function () {
    addPublicLanguage('es', 'es', true);
    addPublicLanguage('es_MX', 'es-mx');

    expect(fn () => app(LanguageManager::class)->setUrlGeneral('es_MX'))
        ->toThrow(LanguageOperationException::class)
        ->and((bool) DB::table('languages')->where('locale', 'es')->value('is_url_general'))->toBeTrue()
        ->and((bool) DB::table('languages')->where('locale', 'es_MX')->value('is_url_general'))->toBeFalse();
});

test('browser negotiation ignores zero-quality and unsupported primary language subtags', function () {
    addPublicLanguage('es_ES', 'es-es');
    addPublicLanguage('as_ST', 'as');

    $this->withHeaders(['Accept-Language' => 'es-ES;q=0, en;q=0.5'])->get('/')
        ->assertOk()
        ->assertSessionHas('public_locale', 'en');

    $this->flushSession();

    $this->withHeaders(['Accept-Language' => 'ast, en;q=0.5'])->get('/')
        ->assertOk()
        ->assertSessionHas('public_locale', 'en');
});
