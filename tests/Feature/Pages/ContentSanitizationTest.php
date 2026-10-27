<?php

use App\Models\AdminAuditEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Template\Services\TemplateManager;
use Modules\Core\Template\Services\TemplatePresentationResolver;
use Modules\Core\Template\Services\TemplateUiCatalogs;
use Modules\Pages\Contracts\ContentType;
use Modules\Pages\Contracts\SanitizationPolicy;
use Modules\Pages\Exceptions\PageOperationException;
use Modules\Pages\Models\PageTranslationContent;
use Modules\Pages\Services\ContentEditorRegistry;
use Modules\Pages\Services\ContentSanitizer;
use Modules\Pages\Services\PageManager;
use Modules\Pages\Services\PublicPageResolver;
use Modules\Pages\Support\SanitizationPolicyValue;
use Modules\RichText\RichTextType;
use Modules\TinyMCE\Services\TinymceEditor;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->templateRoot = storage_path('framework/testing/pages-sanitize-'.bin2hex(random_bytes(6)));
    copySanitizeTemplateTree(base_path('Templates/Base'), $this->templateRoot.DIRECTORY_SEPARATOR.'Base');
    $this->app->instance(TemplateManager::class, new TemplateManager($this->templateRoot));
    $this->app->instance(TemplatePresentationResolver::class, new TemplatePresentationResolver($this->templateRoot));
    $this->app->instance(TemplateUiCatalogs::class, new TemplateUiCatalogs($this->templateRoot, app('log')));
    $this->app->forgetInstance(PageManager::class);
    $this->app->forgetInstance(PublicPageResolver::class);
});

afterEach(function () {
    removeSanitizeTemplateTree($this->templateRoot);
    $this->app->forgetInstance(TemplateManager::class);
    $this->app->forgetInstance(TemplatePresentationResolver::class);
    $this->app->forgetInstance(TemplateUiCatalogs::class);
    $this->app->forgetInstance(PageManager::class);
    $this->app->forgetInstance(PublicPageResolver::class);
});

function sanitize(string $html): string
{
    return (new TinymceEditor)->sanitize($html, (new RichTextType)->policy());
}

test('the rich-text type declares the tinymce editor and the editor module provides the sanitizer', function () {
    expect((new RichTextType)->editor())->toBe('tinymce')
        ->and(app(ContentEditorRegistry::class)->has('tinymce'))->toBeTrue();
});

test('the server sanitizer is required and missing sanitizers block saving', function () {
    $type = new class implements ContentType
    {
        public function identifier(): string
        {
            return 'acme.custom';
        }

        public function name(): string
        {
            return 'Custom';
        }

        public function editor(): ?string
        {
            return 'missing-editor';
        }

        public function contentKey(): ?string
        {
            return 'body.content';
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

    expect(fn () => app(ContentSanitizer::class)->sanitize($type, '<p>Hello</p>'))
        ->toThrow(PageOperationException::class, 'No server sanitizer');
});

test('server sanitization strips scripts, event handlers and unsafe markup', function () {
    expect(sanitize('<p onclick="alert(1)">Hello <script>alert(2)</script></p>'))->toBe('<p>Hello </p>')
        ->and(sanitize('<img src="x" onerror="alert(1)">'))->toBe('')
        ->and(sanitize('<svg onload="alert(1)"></svg>'))->toBe('')
        ->and(sanitize('<iframe src="https://example.com"></iframe>'))->toBe('')
        ->and(sanitize('<a href="javascript:alert(1)">x</a>'))->toBe('<a>x</a>')
        ->and(sanitize('<div class="arbitrary"><span style="color:red">x</span></div>'))->toBe('<div><span>x</span></div>');
});

test('server sanitization preserves semantic structure and safe links', function () {
    $html = '<h1>Title</h1><p>Text <strong>bold</strong> <a href="https://example.com" title="T">link</a></p>'
        .'<table><thead><tr><th>H</th></tr></thead><tbody><tr><td>D</td></tr></tbody></table>'
        .'<blockquote>Quote</blockquote><pre>Code</pre><section><article>Body</article></section>';

    $result = sanitize($html);

    expect($result)->toContain('<h1>Title</h1>')
        ->toContain('<strong>bold</strong>')
        ->toContain('<a href="https://example.com" title="T">link</a>')
        ->toContain('<table>')
        ->toContain('<blockquote>Quote</blockquote>')
        ->toContain('<pre>Code</pre>')
        ->toContain('<section>')
        ->toContain('<article>');
});

test('direct hostile payloads sent to the domain are sanitized before persistence', function () {
    $pages = app(PageManager::class);
    $page = $pages->create('en', 'about', 'About');

    $content = $pages->setContent($page->page_id, 'en', 'body.content', '<p>Safe</p><script>alert(1)</script><img src=x onerror=alert(2)>');

    expect($content->content)->toBe('<p>Safe</p>');
});

test('the original unsanitized HTML is not retained', function () {
    $pages = app(PageManager::class);
    $page = $pages->create('en', 'about', 'About');

    $pages->setContent($page->page_id, 'en', 'body.content', '<p>Safe</p><script>alert(1)</script>');

    $stored = PageTranslationContent::query()->where('page_translation_id', $page->id)->where('key', 'body.content')->sole();

    expect($stored->content)->toBe('<p>Safe</p>')
        ->and($stored->content)->not->toContain('script');
});

test('audit events for content save do not retain the HTML', function () {
    $pages = app(PageManager::class);
    $page = $pages->create('en', 'about', 'About');

    $pages->setContent($page->page_id, 'en', 'body.content', '<p>Secret content</p><script>alert(1)</script>');

    $event = AdminAuditEvent::query()->where('operation', 'page.content.save')->sole();

    expect(json_encode($event->after_state))->not->toContain('Secret content')
        ->and(json_encode($event->after_state))->not->toContain('<script>')
        ->and($event->after_state)->toMatchArray(['page_translation_id' => $page->id, 'key' => 'body.content']);
});

function copySanitizeTemplateTree(string $source, string $destination): void
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

function removeSanitizeTemplateTree(string $path): void
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
