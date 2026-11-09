<?php

use App\Models\AdminAuditEvent;
use App\Services\Admin\AdminAuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Modules\Core\Language\Events\LanguageInstalled;
use Modules\Core\Language\Models\Language;
use Modules\Core\Template\Services\TemplateManager;
use Modules\Core\Template\Services\TemplatePresentationResolver;
use Modules\Core\Template\Services\TemplateUiCatalogs;
use Modules\Navigation\Services\MenuManager;
use Modules\Pages\Contracts\ContentType;
use Modules\Pages\Contracts\SanitizationPolicy;
use Modules\Pages\Exceptions\PageOperationException;
use Modules\Pages\Models\Page;
use Modules\Pages\Models\PageTranslation;
use Modules\Pages\Models\PageTranslationContent;
use Modules\Pages\Services\ContentTypeRegistry;
use Modules\Pages\Services\PageManager;
use Modules\Pages\Services\PublicPageResolver;
use Modules\Pages\Support\SanitizationPolicyValue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->templateRoot = storage_path('framework/testing/pages-domain-'.bin2hex(random_bytes(6)));
    copyDomainTemplateTree(base_path('Templates/Base'), $this->templateRoot.DIRECTORY_SEPARATOR.'Base');
    $this->app->instance(TemplateManager::class, new TemplateManager($this->templateRoot));
    $this->app->instance(TemplatePresentationResolver::class, new TemplatePresentationResolver($this->templateRoot));
    $this->app->instance(TemplateUiCatalogs::class, new TemplateUiCatalogs($this->templateRoot, app('log')));
    $this->app->forgetInstance(PageManager::class);
    $this->app->forgetInstance(PublicPageResolver::class);
});

afterEach(function () {
    removeDomainTemplateTree($this->templateRoot);
    $this->app->forgetInstance(TemplateManager::class);
    $this->app->forgetInstance(TemplatePresentationResolver::class);
    $this->app->forgetInstance(TemplateUiCatalogs::class);
    $this->app->forgetInstance(PageManager::class);
    $this->app->forgetInstance(PublicPageResolver::class);
});

function domainLanguage(string $locale, string $prefix): void
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
}

test('a page with descendants cannot be deleted', function () {
    $pages = app(PageManager::class);
    $root = $pages->create('en', 'root', 'Root', name: 'Root');
    $pages->create('en', 'child', 'Child', $root->page_id, name: 'Child');

    expect(fn () => $pages->delete($root->page_id))
        ->toThrow(PageOperationException::class, 'descendants');
});

test('an internal name is trimmed, normalized and rejects invalid or duplicate values atomically', function () {
    $pages = app(PageManager::class);
    $auditEvents = AdminAuditEvent::query()->count();
    $page = $pages->create('en', 'about', 'About', name: "  Caf\u{00E9}  ");

    expect($page->page->name)->toBe('Café')
        ->and($page->page->name_key)->toBe('café')
        ->and(fn () => $pages->create('en', 'other', 'Other', name: "CAFE\u{0301}"))
        ->toThrow(PageOperationException::class, 'conflicts')
        ->and(fn () => $pages->create('en', 'invalid', 'Invalid', name: "Bad\nname"))
        ->toThrow(PageOperationException::class, 'internal page name is invalid')
        ->and(fn () => $pages->create('en', 'missing', 'Missing'))
        ->toThrow(PageOperationException::class, 'internal page name is required')
        ->and(fn () => $pages->create('en', 'empty', 'Empty', name: '   '))
        ->toThrow(PageOperationException::class, 'internal page name is invalid')
        ->and(fn () => $pages->create('en', 'forbidden', 'Forbidden', name: 'Not/allowed'))
        ->toThrow(PageOperationException::class, 'internal page name is invalid')
        ->and(fn () => $pages->create('en', 'backslash', 'Backslash', name: 'Foo\\Bar'))
        ->not->toThrow(PageOperationException::class)
        ->and(fn () => $pages->create('en', 'too-long', 'Too long', name: str_repeat('é', 151)))
        ->toThrow(PageOperationException::class, 'internal page name is invalid')
        ->and(AdminAuditEvent::query()->count())->toBe($auditEvents + 2);
});

test('the database index rejects a duplicate normalized page name independently of the service', function () {
    $pages = app(PageManager::class);
    $pages->create('en', 'unique-page', 'Unique', name: 'Unique Name');

    expect(fn () => DB::table('pages')->insert([
        'name' => 'Unique Name',
        'name_key' => 'unique name',
        'content_type' => 'rich-text',
        'presentation_key' => 'public.page.standard',
        'is_published' => false,
        'uses_explicit_template' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('internal name keys preserve distinct Unicode values that a non-binary collation could equate', function () {
    $pages = app(PageManager::class);
    $sharpS = $pages->create('en', 'sharp-s', 'Sharp S', name: 'Straße');
    $doubleS = $pages->create('en', 'double-s', 'Double S', name: 'Strasse');

    expect($sharpS->page->name_key)->toBe('straße')
        ->and($doubleS->page->name_key)->toBe('strasse');
});

test('a page referenced by a menu item cannot be deleted', function () {
    $pages = app(PageManager::class);
    $page = $pages->create('en', 'about', 'About', name: 'About');
    $menus = app(MenuManager::class);
    $menus->create('footer');
    $menus->createItem('footer', 'en', $page->page_id, 'About');

    expect(fn () => $pages->delete($page->page_id))
        ->toThrow(PageOperationException::class, 'referenced by menu item');
});

test('a deletable page is removed atomically together with translations and contents', function () {
    $pages = app(PageManager::class);
    $page = $pages->create('en', 'about', 'About', name: 'About');
    $pages->setContent($page->page_id, 'en', 'body.content', '<p>Hello</p>');

    $pages->delete($page->page_id);

    expect(Page::query()->find($page->page_id))->toBeNull()
        ->and(PageTranslation::query()->where('page_id', $page->page_id)->count())->toBe(0)
        ->and(PageTranslationContent::query()->where('page_translation_id', $page->id)->count())->toBe(0);
});

test('the page is the only unit of deletion in the domain contract', function () {
    $pages = app(PageManager::class);
    $page = $pages->create('en', 'about', 'About', name: 'About');

    $methods = collect((new ReflectionClass(PageManager::class))->getMethods(ReflectionMethod::IS_PUBLIC))->map->getName()->all();

    expect($methods)->toContain('delete')
        ->and($methods)->not->toContain('deleteTranslation')
        ->and($page->page_id)->toBeInt();
});

test('content types are immutable, validated and not selectable when system', function () {
    $registry = app(ContentTypeRegistry::class);

    expect($registry->get('rich-text')->identifier())->toBe('rich-text')
        ->and($registry->has('system.static'))->toBeTrue()
        ->and($registry->selectable())->not->toHaveKey('system.static')
        ->and(fn () => $registry->get('unknown'))->toThrow(PageOperationException::class);

    $duplicate = new class implements ContentType
    {
        public function identifier(): string
        {
            return 'rich-text';
        }

        public function name(): string
        {
            return 'Duplicate';
        }

        public function editor(): ?string
        {
            return null;
        }

        public function contentKey(): ?string
        {
            return null;
        }

        public function isSystem(): bool
        {
            return false;
        }

        public function policy(): SanitizationPolicy
        {
            return new SanitizationPolicyValue([]);
        }
    };

    expect(fn () => $registry->register($duplicate))->toThrow(PageOperationException::class, 'already registered');
});

test('the content type belongs to the page and is shared by all its translations', function () {
    $pages = app(PageManager::class);
    $page = $pages->create('en', 'about', 'About', name: 'About');

    expect($page->page->content_type)->toBe('rich-text')
        ->and(PageTranslation::query()->where('page_id', $page->page_id)->count())->toBeGreaterThanOrEqual(1)
        ->and(Page::query()->find($page->page_id)->content_type)->toBe('rich-text');
});

test('the system static type cannot be used to create editorial pages', function () {
    $pages = app(PageManager::class);

    expect(fn () => $pages->create('en', 'about', 'About', contentType: 'system.static', name: 'About'))
        ->toThrow(PageOperationException::class, 'cannot be used to create editorial pages')
        ->and(Page::query()->where('content_type', 'system.static')->count())->toBe(1);
});

test('installing a language creates translation stubs for existing pages', function () {
    $pages = app(PageManager::class);
    $page = $pages->create('en', 'about', 'About', name: 'About');

    $now = now();
    $language = Language::query()->create([
        'locale' => 'es_ES',
        'url_prefix' => 'es-es',
        'name' => 'Spanish',
        'native_name' => 'Español',
        'text_direction' => 'ltr',
        'is_active' => false,
        'is_url_general' => false,
        'installed_at' => $now,
    ]);
    LanguageInstalled::dispatch($language);

    $stub = PageTranslation::query()->where('page_id', $page->page_id)->where('language_id', $language->id)->sole();

    expect($stub->title)->toBeNull()
        ->and($stub->slug)->toBeNull()
        ->and($stub->is_published)->toBeFalse();
});

test('content is stored sanitized with uniqueness per translation and key', function () {
    $pages = app(PageManager::class);
    $page = $pages->create('en', 'about', 'About', name: 'About');

    $content = $pages->setContent($page->page_id, 'en', 'body.content', '<p>Hello <strong>world</strong></p>');

    expect($content->content)->toBe('<p>Hello <strong>world</strong></p>')
        ->and(PageTranslationContent::query()->where('page_translation_id', $page->id)->where('key', 'body.content')->count())->toBe(1);

    $pages->setContent($page->page_id, 'en', 'body.content', '<p>Updated</p>');

    expect(PageTranslationContent::query()->where('page_translation_id', $page->id)->where('key', 'body.content')->count())->toBe(1)
        ->and(PageTranslationContent::query()->where('page_translation_id', $page->id)->where('key', 'body.content')->sole()->content)->toBe('<p>Updated</p>');
});

test('editing a translation and its content is atomic when content auditing fails', function () {
    $pages = app(PageManager::class);
    $page = $pages->create('en', 'about', 'About', name: 'About');
    $pages->setContent($page->page_id, 'en', 'body.content', '<p>Original</p>');
    $auditEvents = AdminAuditEvent::query()->count();
    $audit = Mockery::mock(AdminAuditLogger::class, [app('auth')])->makePartial();
    $audit->shouldReceive('record')->with('page.content.save', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
        ->andThrow(new RuntimeException('Audit unavailable'));
    $this->app->instance(AdminAuditLogger::class, $audit);
    $this->app->forgetInstance(PageManager::class);

    expect(fn () => app(PageManager::class)->updateTranslationAndContent($page->page_id, 'en', 'renamed', 'Renamed', '<p>Updated</p>'))
        ->toThrow(RuntimeException::class, 'Audit unavailable');

    expect($page->fresh()->only(['slug', 'title']))->toBe(['slug' => 'about', 'title' => 'About'])
        ->and(PageTranslationContent::query()->where('page_translation_id', $page->id)->where('key', 'body.content')->sole()->content)->toBe('<p>Original</p>')
        ->and(AdminAuditEvent::query()->count())->toBe($auditEvents);
});

test('an external registered type supplies its editor content key and renders without Pages importing it', function () {
    $registry = app(ContentTypeRegistry::class);
    $registry->register(new class implements ContentType
    {
        public function identifier(): string
        {
            return 'acme.article';
        }

        public function name(): string
        {
            return 'Acme article';
        }

        public function editor(): ?string
        {
            return 'tinymce';
        }

        public function contentKey(): ?string
        {
            return 'article.content';
        }

        public function isSystem(): bool
        {
            return false;
        }

        public function policy(): SanitizationPolicy
        {
            return new SanitizationPolicyValue(['p' => []]);
        }
    });
    $pages = app(PageManager::class);
    $page = $pages->create('en', 'external', 'External', contentType: 'acme.article', name: 'External');

    $pages->updateTranslationAndContent($page->page_id, 'en', 'external', 'External', '<p>External content</p>');
    $pages->publish($page->page_id, 'en');

    $this->get('/external')->assertOk()->assertSee('External content');
    expect(file_get_contents(base_path('Modules/Pages/app/Http/Controllers/Admin/PageController.php')))->not->toContain('Modules\\RichText');
});

test('the content column is longtext and the 4 MiB limit is enforced before sanitization', function () {
    $pages = app(PageManager::class);
    $page = $pages->create('en', 'about', 'About', name: 'About');

    $expectedType = DB::getDriverName() === 'sqlite' ? 'text' : 'longtext';

    expect(Schema::getColumnType('page_translation_contents', 'content'))->toBe($expectedType);

    expect(fn () => $pages->setContent($page->page_id, 'en', 'body.content', str_repeat('a', 4 * 1024 * 1024 + 1)))
        ->toThrow(PageOperationException::class, '4 MiB');
});

test('an invalid content key is rejected', function () {
    $pages = app(PageManager::class);
    $page = $pages->create('en', 'about', 'About', name: 'About');

    expect(fn () => $pages->setContent($page->page_id, 'en', 'Body::Content', '<p>x</p>'))
        ->toThrow(PageOperationException::class, 'content key is invalid');
});

test('content fallback uses the frontend default language in base mode', function () {
    config(['core.ui_catalog_fallback_mode' => 'base']);
    $pages = app(PageManager::class);
    $page = $pages->create('en', 'about', 'About', name: 'About');
    $pages->setContent($page->page_id, 'en', 'body.content', '<p>Hello</p>');
    domainLanguage('es_ES', 'es-es');
    $pages->addLanguageStubs(Language::query()->where('locale', 'es_ES')->firstOrFail());

    expect($pages->content($page->page_id, 'es_ES', 'body.content'))->toBe('<p>Hello</p>');
});

test('content fallback returns the literal key in key mode', function () {
    config(['core.ui_catalog_fallback_mode' => 'key']);
    $pages = app(PageManager::class);
    $page = $pages->create('en', 'about', 'About', name: 'About');
    $pages->setContent($page->page_id, 'en', 'body.content', '<p>Hello</p>');
    domainLanguage('es_ES', 'es-es');
    $pages->addLanguageStubs(Language::query()->where('locale', 'es_ES')->firstOrFail());

    expect($pages->content($page->page_id, 'es_ES', 'body.content'))->toBe('body.content');
});

test('content fallback follows the frontend default dynamically', function () {
    config(['core.ui_catalog_fallback_mode' => 'base']);
    $pages = app(PageManager::class);
    $page = $pages->create('en', 'about', 'About', name: 'About');
    domainLanguage('es_ES', 'es-es');
    $pages->addLanguageStubs(Language::query()->where('locale', 'es_ES')->firstOrFail());
    $pages->setContent($page->page_id, 'es_ES', 'body.content', '<p>Hola</p>');

    expect($pages->content($page->page_id, 'en', 'body.content'))->toBe('body.content');

    DB::table('language_settings')->where('id', 1)->update([
        'frontend_default_language_id' => Language::query()->where('locale', 'es_ES')->value('id'),
    ]);

    expect($pages->content($page->page_id, 'en', 'body.content'))->toBe('<p>Hola</p>');
});

test('a published page can change its slug and be moved atomically', function () {
    $pages = app(PageManager::class);
    $root = $pages->create('en', 'section', 'Section', name: 'Section');
    $pages->publish($root->page_id, 'en');
    $child = $pages->create('en', 'child', 'Child', $root->page_id, name: 'Child');
    $pages->publish($child->page_id, 'en');

    $pages->translate($child->page_id, 'en', 'renamed', 'Renamed');
    expect($child->fresh()->slug)->toBe('renamed');

    $other = $pages->create('en', 'other', 'Other', name: 'Other');
    $pages->publish($other->page_id, 'en');
    $pages->move($child->page_id, $other->page_id);

    expect(Page::query()->find($child->page_id)->parent_id)->toBe($other->page_id);
});

test('a move that would leave a published translation inaccessible is rejected', function () {
    domainLanguage('es_ES', 'es-es');
    $pages = app(PageManager::class);
    $root = $pages->create('en', 'section', 'Section', name: 'Section');
    $pages->publish($root->page_id, 'en');
    $pages->translate($root->page_id, 'es_ES', 'seccion', 'Sección');
    $pages->publish($root->page_id, 'es_ES');

    $draftParent = $pages->create('en', 'draft-parent', 'Draft parent', name: 'Draft parent');

    expect(fn () => $pages->move($root->page_id, $draftParent->page_id))
        ->toThrow(PageOperationException::class, 'inaccessible');
});

function copyDomainTemplateTree(string $source, string $destination): void
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

function removeDomainTemplateTree(string $path): void
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
