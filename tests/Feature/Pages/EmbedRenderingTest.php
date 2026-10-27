<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Modules\Core\Template\Models\CmsTemplate;
use Modules\Pages\Contracts\EmbedType;
use Modules\Pages\Models\PageTranslationContent;
use Modules\Pages\Services\EmbedTypeRegistry;
use Modules\Pages\Services\PageManager;
use Modules\Pages\Support\ResolvedEmbed;

uses(RefreshDatabase::class);

test('an embed marker is resolved in place and removed from the rendered HTML', function () {
    app(EmbedTypeRegistry::class)->register(new class implements EmbedType
    {
        public function identifier(): string
        {
            return 'acme.video';
        }

        public function variants(): array
        {
            return ['inline', 'modal'];
        }

        public function defaultVariant(): string
        {
            return 'inline';
        }

        public function resolve(string $reference): ?array
        {
            return ['reference' => $reference, 'kind' => 'video'];
        }
    });

    $pages = app(PageManager::class);
    $page = $pages->create('en', 'about', 'About');
    $pages->setContent($page->page_id, 'en', 'body.content', '<p>Before</p><cms-embed data-type="acme.video" data-ref="abc" data-variant="inline"></cms-embed><p>After</p>');
    $pages->publish($page->page_id, 'en');

    $response = $this->get('/about');

    $response->assertOk()
        ->assertDontSee('<cms-embed', false)
        ->assertSee('data-embed-type="acme.video"', false);

    $html = $response->getContent();

    expect(strpos($html, 'Before'))->toBeLessThan(strpos($html, 'data-embed-type'))
        ->and(strpos($html, 'data-embed-type'))->toBeLessThan(strpos($html, 'After'));
});

test('rendering a non-degraded embed returns valid HTML without Blade re-interpretation', function () {
    $template = CmsTemplate::query()->where('identifier', 'base')->sole();
    $embed = new ResolvedEmbed('acme.video', 'inline', ['reference' => 'abc', 'kind' => 'video'], false);

    $html = Blade::render('<x-cms-embed :embed="$embed" :effective-template="$template" :locale="$locale" />', [
        'embed' => $embed,
        'template' => $template,
        'locale' => 'en',
    ]);

    expect($html)->toContain('data-embed-type="acme.video"')
        ->and($html)->toContain('data-embed-variant="inline"')
        ->and($html)->toContain('Embedded content');
});

test('rendering content with an invalid marker grammar degrades without a 500 and without the marker', function () {
    $pages = app(PageManager::class);
    $page = $pages->create('en', 'about', 'About');
    PageTranslationContent::query()->create([
        'page_translation_id' => $page->id,
        'key' => 'body.content',
        'content' => '<p>Before</p><cms-embed data-type="../../secret" data-ref="x"></cms-embed><p>After</p>',
    ]);
    $pages->publish($page->page_id, 'en');

    $response = $this->get('/about');

    $response->assertOk()
        ->assertDontSee('<cms-embed', false)
        ->assertSee('Before')
        ->assertSee('After');
});

test('an unknown embed type renders a degraded placeholder without the marker', function () {
    $pages = app(PageManager::class);
    $page = $pages->create('en', 'about', 'About');
    $pages->setContent($page->page_id, 'en', 'body.content', '<p>Before</p><cms-embed data-type="unknown.type" data-ref="abc"></cms-embed><p>After</p>');
    $pages->publish($page->page_id, 'en');

    $response = $this->get('/about');

    $response->assertOk()
        ->assertDontSee('<cms-embed', false)
        ->assertSee('data-embed-degraded="1"', false)
        ->assertSee('data-embed-type="unknown.type"', false);
});

test('a self-closing embed marker is normalized and the following content is preserved', function () {
    app(EmbedTypeRegistry::class)->register(new class implements EmbedType
    {
        public function identifier(): string
        {
            return 'acme.video';
        }

        public function variants(): array
        {
            return ['inline'];
        }

        public function defaultVariant(): string
        {
            return 'inline';
        }

        public function resolve(string $reference): ?array
        {
            return ['reference' => $reference];
        }
    });

    $pages = app(PageManager::class);
    $page = $pages->create('en', 'about', 'About');
    $pages->setContent($page->page_id, 'en', 'body.content', '<p>a</p><cms-embed data-type="acme.video" data-ref="abc"/><p>b</p>');
    $pages->publish($page->page_id, 'en');

    $response = $this->get('/about');

    $response->assertOk()
        ->assertDontSee('<cms-embed', false)
        ->assertSee('data-embed-type="acme.video"', false)
        ->assertSee('<p>b</p>', false);

    $html = $response->getContent();

    expect(strpos($html, 'data-embed-type'))->toBeLessThan(strpos($html, '<p>b</p>'));
});
