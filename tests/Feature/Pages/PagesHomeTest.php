<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Core\Language\Models\Language;
use Modules\Core\Template\Services\TemplateManager;
use Modules\Core\Template\Services\TemplatePresentationResolver;
use Modules\Core\Template\Services\TemplateUiCatalogs;
use Modules\Navigation\Exceptions\NavigationOperationException;
use Modules\Navigation\Models\Menu;
use Modules\Navigation\Models\MenuItemHome;
use Modules\Navigation\Services\MenuManager;
use Modules\Pages\Exceptions\PageOperationException;
use Modules\Pages\Services\PageManager;
use Modules\Pages\Services\PublicPageResolver;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->templateRoot = storage_path('framework/testing/pages-home-'.bin2hex(random_bytes(6)));
    copyHomeTemplateTree(base_path('Templates/Base'), $this->templateRoot.DIRECTORY_SEPARATOR.'Base');
    $this->app->instance(TemplateManager::class, new TemplateManager($this->templateRoot));
    $this->app->instance(TemplatePresentationResolver::class, new TemplatePresentationResolver($this->templateRoot));
    $this->app->instance(TemplateUiCatalogs::class, new TemplateUiCatalogs($this->templateRoot, app('log')));
    $this->app->forgetInstance(PageManager::class);
    $this->app->forgetInstance(PublicPageResolver::class);
});

afterEach(function () {
    removeHomeTemplateTree($this->templateRoot);
    $this->app->forgetInstance(TemplateManager::class);
    $this->app->forgetInstance(TemplatePresentationResolver::class);
    $this->app->forgetInstance(TemplateUiCatalogs::class);
    $this->app->forgetInstance(PageManager::class);
    $this->app->forgetInstance(PublicPageResolver::class);
});

function homeLanguage(string $locale, string $prefix): void
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

function homeMenu(string $locale, int $pageId): int
{
    $menus = app(MenuManager::class);
    $menu = Menu::query()->where('identifier', 'home')->first() ?? $menus->create('home');
    $item = $menus->createItem('home', $locale, $pageId, 'Home');

    return $item->id;
}

test('a home is marked from a menu item and is unique per language across the system', function () {
    $pages = app(PageManager::class);
    $english = $pages->create('en', 'about', 'About');
    $pages->publish($english->page_id, 'en');
    homeLanguage('es_ES', 'es-es');
    $spanish = $pages->create('es_ES', 'inicio', 'Inicio');
    $pages->publish($spanish->page_id, 'es_ES');

    $menus = app(MenuManager::class);
    $menus->create('footer');
    $enItem = $menus->createItem('footer', 'en', $english->page_id, 'Home en');
    $esItem = $menus->createItem('footer', 'es_ES', $spanish->page_id, 'Home es');
    $menus->markHome($enItem->id);
    $menus->markHome($esItem->id);

    expect(MenuItemHome::query()->count())->toBe(2)
        ->and(MenuItemHome::query()->where('language_id', DB::table('languages')->where('locale', 'en')->value('id'))->value('menu_item_id'))->toBe($enItem->id)
        ->and(MenuItemHome::query()->where('language_id', DB::table('languages')->where('locale', 'es_ES')->value('id'))->value('menu_item_id'))->toBe($esItem->id);
});

test('marking a home requires a publicly available page and one home per language', function () {
    $pages = app(PageManager::class);
    $draft = $pages->create('en', 'draft', 'Draft');
    $menus = app(MenuManager::class);
    $menus->create('footer');
    $draftItem = $menus->createItem('footer', 'en', $draft->page_id, 'Draft');

    expect(fn () => $menus->markHome($draftItem->id))
        ->toThrow(NavigationOperationException::class, 'publicly available');

    $published = $pages->create('en', 'published', 'Published');
    $pages->publish($published->page_id, 'en');
    $publishedItem = $menus->createItem('footer', 'en', $published->page_id, 'Published');
    $menus->markHome($publishedItem->id);

    $replacement = $pages->create('en', 'replacement', 'Replacement');
    $pages->publish($replacement->page_id, 'en');
    $replacementItem = $menus->createItem('footer', 'en', $replacement->page_id, 'Replacement');
    $menus->markHome($replacementItem->id);

    expect(MenuItemHome::query()->count())->toBe(1)
        ->and(MenuItemHome::query()->where('language_id', DB::table('languages')->where('locale', 'en')->value('id'))->value('menu_item_id'))->toBe($replacementItem->id);
});

test('unpublishing the home translation is blocked', function () {
    $pages = app(PageManager::class);
    $page = $pages->create('en', 'about', 'About');
    $pages->publish($page->page_id, 'en');
    $menus = app(MenuManager::class);
    $menus->create('footer');
    $item = $menus->createItem('footer', 'en', $page->page_id, 'Home');
    $menus->markHome($item->id);

    expect(fn () => $pages->unpublish($page->page_id, 'en'))
        ->toThrow(PageOperationException::class);
});

test('unpublishing an ancestor of the home is blocked', function () {
    $pages = app(PageManager::class);
    $root = $pages->create('en', 'section', 'Section');
    $pages->publish($root->page_id, 'en');
    $child = $pages->create('en', 'child', 'Child', $root->page_id);
    $pages->publish($child->page_id, 'en');
    $menus = app(MenuManager::class);
    $menus->create('footer');
    $item = $menus->createItem('footer', 'en', $child->page_id, 'Home');
    $menus->markHome($item->id);

    expect(fn () => $pages->unpublish($root->page_id, 'en'))
        ->toThrow(PageOperationException::class)
        ->and($root->fresh()->is_published)->toBeTrue();
});

test('a home page cannot be deleted', function () {
    $pages = app(PageManager::class);
    $page = $pages->create('en', 'about', 'About');
    $pages->publish($page->page_id, 'en');
    $menus = app(MenuManager::class);
    $menus->create('footer');
    $item = $menus->createItem('footer', 'en', $page->page_id, 'Home');
    $menus->markHome($item->id);

    expect(fn () => $pages->delete($page->page_id))
        ->toThrow(PageOperationException::class, 'home');
});

test('leaving a language without a home makes its root respond 404 while login remains available', function () {
    $pages = app(PageManager::class);
    $page = $pages->create('en', 'about', 'About');
    $pages->publish($page->page_id, 'en');
    $menus = app(MenuManager::class);
    $menus->create('footer');
    $item = $menus->createItem('footer', 'en', $page->page_id, 'Home');
    $menus->markHome($item->id);
    $this->get('/')->assertOk();

    $menus->unmarkHome('en');

    $this->get('/')->assertNotFound();
    $this->get('/admin/login')->assertOk();
});

test('the root resolves the destination returned by Navigation', function () {
    $pages = app(PageManager::class);
    $page = $pages->create('en', 'about', 'About');
    $pages->publish($page->page_id, 'en');
    $menus = app(MenuManager::class);
    $menus->create('footer');
    $item = $menus->createItem('footer', 'en', $page->page_id, 'Home');
    $menus->markHome($item->id);

    $this->get('/')->assertOk()->assertSee('About');
});

test('only the seeded construction page is published on installation', function () {
    expect(MenuItemHome::query()->count())->toBe(1);

    $pages = app(PageManager::class);
    $draft = $pages->create('en', 'draft', 'Draft');

    expect($draft->is_published)->toBeFalse()
        ->and($draft->page->fresh()->is_published)->toBeFalse();
});

function copyHomeTemplateTree(string $source, string $destination): void
{
    mkdir($destination, recursive: true);
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );

    foreach ($iterator as $entry) {
        $target = $destination.DIRECTORY_SEPARATOR.$iterator->getSubPathName();
        $entry->isDir() ? mkdir($target) : copy($entry->getPathname(), $target);
    }
}

function removeHomeTemplateTree(string $path): void
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
