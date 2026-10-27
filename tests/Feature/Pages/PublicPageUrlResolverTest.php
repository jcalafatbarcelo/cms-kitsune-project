<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Core\Language\Models\Language;
use Modules\Navigation\Models\Menu;
use Modules\Navigation\Services\MenuManager;
use Modules\Pages\Services\PageManager;
use Modules\Pages\Services\PublicPageUrlResolver;

uses(RefreshDatabase::class);

function urlResolverLanguage(string $locale, string $prefix): void
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
    app(PageManager::class)->addLanguageStubs(Language::query()->where('locale', $locale)->firstOrFail());
}

function urlResolverMarkHome(string $locale, int $pageId): void
{
    $menus = app(MenuManager::class);
    $menu = Menu::query()->where('identifier', 'home')->first() ?? $menus->create('home');
    $item = $menus->createItem('home', $locale, $pageId, 'Home');
    $menus->markHome($item->id);
}

test('the Pages URL contract returns only canonical URLs for publicly available pages', function () {
    urlResolverLanguage('es_ES', 'es-es');
    $pages = app(PageManager::class);
    $englishHome = DB::table('page_translations')->where('language_id', DB::table('languages')->where('locale', 'en')->value('id'))->sole();
    $pages->translate($englishHome->page_id, 'es_ES', 'inicio', 'Inicio');
    $pages->publish($englishHome->page_id, 'es_ES');
    $child = $pages->create('es_ES', 'equipo', 'Equipo', $englishHome->page_id);
    $pages->publish($child->page_id, 'es_ES');
    urlResolverMarkHome('es_ES', $englishHome->page_id);

    $resolver = app(PublicPageUrlResolver::class);

    expect($resolver->forPage($englishHome->page_id, 'en'))->toBe('/')
        ->and($resolver->forPage($englishHome->page_id, 'es_ES'))->toBe('/es/')
        ->and($resolver->forPage($child->page_id, 'es_ES'))->toBe('/es/inicio/equipo')
        ->and($resolver->forPage($child->page_id, 'en'))->toBeNull()
        ->and($resolver->forPage($child->page_id, 'missing'))->toBeNull()
        ->and($resolver->forPages([$englishHome->page_id, $child->page_id, 999], 'es_ES'))->toBe([
            $englishHome->page_id => '/es/',
            $child->page_id => '/es/inicio/equipo',
        ]);

    DB::table('page_translations')->where('page_id', $englishHome->page_id)
        ->where('language_id', DB::table('languages')->where('locale', 'es_ES')->value('id'))
        ->update(['is_published' => false]);

    expect($resolver->forPage($child->page_id, 'es_ES'))->toBeNull();
});
