<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Core\Template\Exceptions\TemplateOperationException;
use Modules\Core\Template\Models\CmsTemplate;
use Modules\Core\Template\Services\TemplateManager;
use Modules\Core\Template\Services\TemplatePresentationResolver;
use Modules\Core\Template\Services\TemplateUiCatalogs;
use Modules\Pages\Exceptions\PageOperationException;
use Modules\Pages\Models\PageLanguageHome;
use Modules\Pages\Models\PageTranslation;
use Modules\Pages\Services\PageManager;
use Modules\Pages\Services\PublicPageResolver;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->templateRoot = storage_path('framework/testing/pages-templates-'.bin2hex(random_bytes(6)));
    copyPagesTemplateTree(base_path('Templates/Base'), $this->templateRoot.DIRECTORY_SEPARATOR.'Base');
    $this->app->instance(TemplateManager::class, new TemplateManager($this->templateRoot));
    $this->app->instance(TemplatePresentationResolver::class, new TemplatePresentationResolver($this->templateRoot));
    $this->app->instance(TemplateUiCatalogs::class, new TemplateUiCatalogs($this->templateRoot, app('log')));
    $this->app->forgetInstance(PageManager::class);
    $this->app->forgetInstance(PublicPageResolver::class);
});

afterEach(function () {
    removePagesTemplateTree($this->templateRoot);
    $this->app->forgetInstance(TemplateManager::class);
    $this->app->forgetInstance(TemplatePresentationResolver::class);
    $this->app->forgetInstance(TemplateUiCatalogs::class);
    $this->app->forgetInstance(PageManager::class);
    $this->app->forgetInstance(PublicPageResolver::class);
});

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
        'url_prefix' => 'es-es',
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

test('automatic home assignment rejects a page whose ancestor is not public in its language', function () {
    $now = now();
    DB::table('languages')->insert([
        'locale' => 'es_ES',
        'url_prefix' => 'es-es',
        'name' => 'Spanish',
        'native_name' => 'Español',
        'text_direction' => 'ltr',
        'is_active' => true,
        'installed_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $pages = app(PageManager::class);
    $root = PageTranslation::query()->sole();
    $languageId = DB::table('languages')->where('locale', 'es_ES')->value('id');

    expect(fn () => $pages->create('es_ES', 'inicio', 'Inicio', $root->page_id))
        ->toThrow(PageOperationException::class, 'The home page must be publicly available.')
        ->and(PageLanguageHome::query()->where('language_id', $languageId)->exists())->toBeFalse()
        ->and(PageTranslation::query()->where('slug', 'inicio')->exists())->toBeFalse();

    $child = $pages->create('en', 'child', 'Child', $root->page_id);

    expect(fn () => $pages->translate($child->page_id, 'es_ES', 'hijo', 'Hijo'))
        ->toThrow(PageOperationException::class, 'The home page must be publicly available.')
        ->and(PageLanguageHome::query()->where('language_id', $languageId)->exists())->toBeFalse()
        ->and(PageTranslation::query()->where('slug', 'hijo')->exists())->toBeFalse();
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

    $catalog = $this->templateRoot.DIRECTORY_SEPARATOR.'Base'.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'lang'.DIRECTORY_SEPARATOR.'en.json';
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
    $catalog = $this->templateRoot.DIRECTORY_SEPARATOR.'Base'.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'lang'.DIRECTORY_SEPARATOR.'en.json';
    $missingCatalog = $catalog.'.missing';
    rename($catalog, $missingCatalog);

    try {
        $this->get('/')->assertServiceUnavailable();
    } finally {
        rename($missingCatalog, $catalog);
    }
});

test('the public route returns service unavailable when the persisted template hash no longer matches', function () {
    CmsTemplate::query()->where('identifier', 'base')->update(['manifest_hash' => str_repeat('0', 64)]);

    $this->get('/')->assertServiceUnavailable();
});

test('the public route returns service unavailable without paths when the Base presentation is missing or invalid', function () {
    $manifest = $this->templateRoot.DIRECTORY_SEPARATOR.'Base'.DIRECTORY_SEPARATOR.'template.json';
    $data = json_decode(file_get_contents($manifest), true, flags: JSON_THROW_ON_ERROR);
    $data['presentations'] = ['public.navigation.menu'];
    file_put_contents($manifest, json_encode($data, JSON_THROW_ON_ERROR));
    app(TemplateManager::class)->sync();

    $this->get('/')
        ->assertServiceUnavailable()
        ->assertDontSee($this->templateRoot);

    $data['presentations'] = ['public.page.standard', 'public.navigation.menu'];
    file_put_contents($manifest, json_encode($data, JSON_THROW_ON_ERROR));
    app(TemplateManager::class)->sync();
    unlink($this->templateRoot.DIRECTORY_SEPARATOR.'Base'.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'page'.DIRECTORY_SEPARATOR.'standard.blade.php');

    $this->get('/')
        ->assertServiceUnavailable()
        ->assertDontSee($this->templateRoot);
});

test('the public route returns service unavailable without paths when a synchronized manifest is absent or invalid', function () {
    $manifest = $this->templateRoot.DIRECTORY_SEPARATOR.'Base'.DIRECTORY_SEPARATOR.'template.json';
    app(TemplateManager::class)->sync();
    rename($manifest, $manifest.'.missing');

    $this->get('/')
        ->assertServiceUnavailable()
        ->assertDontSee($this->templateRoot);

    copy($this->templateRoot.DIRECTORY_SEPARATOR.'Base'.DIRECTORY_SEPARATOR.'template.json.missing', $manifest);
    file_put_contents($manifest, '{');

    $this->get('/')
        ->assertServiceUnavailable()
        ->assertDontSee($this->templateRoot);
});

test('page assignment is rejected when neither the effective template nor Base resolves its presentation', function () {
    $manifest = $this->templateRoot.DIRECTORY_SEPARATOR.'Base'.DIRECTORY_SEPARATOR.'template.json';
    $data = json_decode(file_get_contents($manifest), true, flags: JSON_THROW_ON_ERROR);
    $data['presentations'] = ['public.navigation.menu'];
    file_put_contents($manifest, json_encode($data, JSON_THROW_ON_ERROR));
    $path = $this->templateRoot.DIRECTORY_SEPARATOR.'Acme';
    mkdir($path.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'navigation', recursive: true);
    file_put_contents($path.DIRECTORY_SEPARATOR.'template.json', json_encode([
        'schema_version' => 1,
        'identifier' => 'acme',
        'name' => 'Acme',
        'presentations' => ['public.navigation.menu'],
    ], JSON_THROW_ON_ERROR));
    file_put_contents($path.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'navigation'.DIRECTORY_SEPARATOR.'menu.blade.php', '<nav></nav>');
    app(TemplateManager::class)->sync();
    app(TemplateManager::class)->activate('acme');

    expect(fn () => app(PageManager::class)->create('en', 'unrenderable', 'Unrenderable', templateIdentifier: 'acme'))
        ->toThrow(TemplateOperationException::class, 'required template presentation is unavailable');
});

test('a page can inherit the Base presentation while its custom template overrides its UI text', function () {
    $path = $this->templateRoot.DIRECTORY_SEPARATOR.'Acme';
    mkdir($path.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'navigation', recursive: true);
    mkdir($path.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'lang', recursive: true);
    file_put_contents($path.DIRECTORY_SEPARATOR.'template.json', json_encode([
        'schema_version' => 1,
        'identifier' => 'acme',
        'name' => 'Acme',
        'presentations' => ['public.navigation.menu'],
    ], JSON_THROW_ON_ERROR));
    file_put_contents($path.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'navigation'.DIRECTORY_SEPARATOR.'menu.blade.php', '<nav>Custom navigation</nav>');
    file_put_contents($path.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'lang'.DIRECTORY_SEPARATOR.'en.json', json_encode([
        'acme::page.home.under-construction.heading' => 'Custom construction',
    ], JSON_THROW_ON_ERROR));

    $templates = app(TemplateManager::class);
    $templates->sync();
    $templates->activate('acme');
    $page = app(PageManager::class)->create('en', 'custom', 'Custom', templateIdentifier: 'acme');
    app(PageManager::class)->publish($page->page_id, 'en');

    $this->get('/custom')
        ->assertOk()
        ->assertSee('Custom construction')
        ->assertSee('This site is being prepared.');
});

function copyPagesTemplateTree(string $source, string $destination): void
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

function removePagesTemplateTree(string $path): void
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
