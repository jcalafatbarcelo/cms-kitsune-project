<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Mockery;
use Modules\Navigation\Exceptions\NavigationOperationException;
use Modules\Navigation\Models\Menu;
use Modules\Navigation\Models\MenuItem;
use Modules\Navigation\Services\MenuManager;
use Modules\Navigation\Services\PublicMenuResolver;
use Modules\Pages\Services\PageManager;
use Modules\Pages\Services\PublicPageUrlResolver;

uses(RefreshDatabase::class);

function navigationLanguage(string $locale, string $prefix, bool $active = true): void
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

function navigationPage(string $slug = 'about'): int
{
    return app(PageManager::class)->create('en', $slug, ucfirst($slug))->page_id;
}

test('menus have immutable technical identifiers and localized trees are isolated', function () {
    navigationLanguage('es_ES', 'es-es');
    $menus = app(MenuManager::class);
    $menus->create('main-menu');
    $menus->create('footer');
    $page = navigationPage();
    $english = $menus->createItem('main-menu', 'en', $page, 'About');
    $spanish = $menus->createItem('main-menu', 'es_ES', $page, 'Acerca de');

    expect(fn () => $menus->create('main-menu'))->toThrow(NavigationOperationException::class)
        ->and(fn () => $menus->createItem('main-menu', 'en', $page, 'Invalid', $spanish->id))->toThrow(NavigationOperationException::class)
        ->and(fn () => $menus->createItem('footer', 'en', $page, 'Invalid', $english->id))->toThrow(NavigationOperationException::class)
        ->and(Menu::query()->where('identifier', 'main-menu')->sole()->identifier)->toBe('main-menu')
        ->and(MenuItem::query()->where('language_id', $spanish->language_id)->sole()->label)->toBe('Acerca de');
});

test('item commands maintain contiguous positions, move subtrees, and reject invalid mutations atomically', function () {
    $home = DB::table('page_translations')->where('language_id', DB::table('languages')->where('locale', 'en')->value('id'))->sole();
    $this->artisan('cms:menu:create', ['identifier' => 'main-menu'])->assertSuccessful();
    $this->artisan('cms:menu:item:create', ['menu' => 'main-menu', 'locale' => 'en', 'page' => $home->page_id, 'label' => 'One'])->assertSuccessful();
    $first = MenuItem::query()->sole();
    $secondPage = navigationPage('second');
    $this->artisan('cms:menu:item:create', ['menu' => 'main-menu', 'locale' => 'en', 'page' => $secondPage, 'label' => 'Two', '--position' => 1])->assertSuccessful();
    $second = MenuItem::query()->where('label', 'Two')->sole();
    $child = app(MenuManager::class)->createItem('main-menu', 'en', $home->page_id, 'Child', $first->id);

    $this->artisan('cms:menu:item:move', ['item' => $first->id, 'position' => 2])->assertSuccessful();

    expect(MenuItem::query()->whereNull('parent_id')->orderBy('position')->pluck('label')->all())->toBe(['Two', 'One'])
        ->and(fn () => app(MenuManager::class)->moveItem($first->id, 1, $child->id))->toThrow(NavigationOperationException::class)
        ->and(fn () => app(MenuManager::class)->createItem('main-menu', 'en', $home->page_id, 'Invalid', null, 4))->toThrow(NavigationOperationException::class)
        ->and(MenuItem::query()->whereNull('parent_id')->orderBy('position')->pluck('position')->all())->toBe([1, 2]);

    $this->artisan('cms:menu:item:remove', ['item' => $first->id])->expectsOutputToContain('cannot be removed')->assertFailed();
    $this->artisan('cms:menu:item:move', ['item' => $child->id, 'position' => 1])->assertSuccessful();
    $this->artisan('cms:menu:item:remove', ['item' => $first->id])->assertSuccessful();

    expect(MenuItem::query()->whereNull('parent_id')->orderBy('position')->pluck('label')->all())->toBe(['Child', 'Two']);
});

test('item update changes its localized label and destination without changing its tree placement', function () {
    $menus = app(MenuManager::class);
    $menus->create('main-menu');
    $firstPage = navigationPage('first');
    $item = $menus->createItem('main-menu', 'en', $firstPage, 'First');
    $secondPage = navigationPage('second');

    $this->artisan('cms:menu:item:update', ['item' => $item->id, 'page' => $secondPage, 'label' => 'Second'])->assertSuccessful();

    expect($item->fresh()->only(['page_id', 'label', 'parent_id', 'position']))->toBe([
        'page_id' => $secondPage,
        'label' => 'Second',
        'parent_id' => null,
        'position' => 1,
    ]);
});

test('database persistence rejects a menu item position below one', function () {
    $menu = app(MenuManager::class)->create('main-menu');
    $languageId = DB::table('languages')->where('locale', 'en')->value('id');
    $pageId = DB::table('page_translations')->where('language_id', $languageId)->value('page_id');

    expect(fn () => DB::table('menu_items')->insert([
        'menu_id' => $menu->id,
        'language_id' => $languageId,
        'page_id' => $pageId,
        'label' => 'Invalid',
        'position' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class)
        ->and(MenuItem::query()->count())->toBe(0);
});

test('the public resolver consumes Pages canonical URLs and filters unavailable subtrees', function () {
    $menus = app(MenuManager::class);
    $menus->create('main-menu');
    $pages = app(PageManager::class);
    $home = DB::table('page_translations')->where('language_id', DB::table('languages')->where('locale', 'en')->value('id'))->sole();
    $unpublished = $pages->create('en', 'draft', 'Draft');
    $parent = $menus->createItem('main-menu', 'en', $unpublished->page_id, 'Draft');
    $menus->createItem('main-menu', 'en', $home->page_id, 'Hidden child', $parent->id);
    $menus->createItem('main-menu', 'en', $home->page_id, 'Home');

    expect(app(PublicMenuResolver::class)->forMenu('main-menu', 'en'))->toBe([
        ['id' => MenuItem::query()->where('label', 'Home')->sole()->id, 'label' => 'Home', 'url' => '/', 'children' => []],
    ])
        ->and(fn () => app(PublicMenuResolver::class)->forMenu('main-menu', 'missing'))->toThrow(NavigationOperationException::class);

    expect(Blade::render('<x-navigation-menu identifier="main-menu" locale="en" />'))->toContain('Home')
        ->not->toContain('Draft')
        ->not->toContain('Hidden child');
});

test('the public resolver deduplicates destinations and requests Pages URLs once', function () {
    $menus = app(MenuManager::class);
    $menus->create('main-menu');
    $pageId = DB::table('page_translations')->where('language_id', DB::table('languages')->where('locale', 'en')->value('id'))->value('page_id');
    $parent = $menus->createItem('main-menu', 'en', $pageId, 'Parent');
    $child = $menus->createItem('main-menu', 'en', $pageId, 'Child', $parent->id);
    $pages = Mockery::mock(PublicPageUrlResolver::class);
    $pages->shouldReceive('forPages')->once()->with([$pageId], 'en')->andReturn([$pageId => '/']);

    expect((new PublicMenuResolver($pages))->forMenu('main-menu', 'en'))->toBe([
        ['id' => $parent->id, 'label' => 'Parent', 'url' => '/', 'children' => [
            ['id' => $child->id, 'label' => 'Child', 'url' => '/', 'children' => []],
        ]],
    ]);
});

test('adding same-depth distinct menu targets does not add Pages resolution queries per item', function () {
    $menus = app(MenuManager::class);
    $menus->create('main-menu');
    $home = DB::table('page_translations')->where('language_id', DB::table('languages')->where('locale', 'en')->value('id'))->sole();
    $menus->createItem('main-menu', 'en', $home->page_id, 'Home');
    $resolver = app(PublicMenuResolver::class);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $resolver->forMenu('main-menu', 'en');
    $oneTargetQueries = count(DB::getQueryLog());

    foreach (['about', 'team', 'contact'] as $slug) {
        $menus->createItem('main-menu', 'en', navigationPage($slug), ucfirst($slug));
    }
    DB::flushQueryLog();
    $resolver->forMenu('main-menu', 'en');
    $manyTargetQueries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($manyTargetQueries)->toBe($oneTargetQueries);
});

test('the public resolver rejects inactive languages without exposing menu items', function () {
    navigationLanguage('fr_FR', 'fr-fr', false);
    app(MenuManager::class)->create('main-menu');

    expect(fn () => app(PublicMenuResolver::class)->forMenu('main-menu', 'fr_FR'))
        ->toThrow(NavigationOperationException::class, 'not active');
});
