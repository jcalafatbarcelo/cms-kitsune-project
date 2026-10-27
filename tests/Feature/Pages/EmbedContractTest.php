<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Template\Enums\CmsPresentation;
use Modules\Core\Template\Models\CmsTemplate;
use Modules\Core\Template\Services\TemplatePresentationResolver;
use Modules\Pages\Contracts\EmbedType;
use Modules\Pages\Exceptions\PageOperationException;
use Modules\Pages\Services\EmbedMarkerParser;
use Modules\Pages\Services\EmbedResolver;
use Modules\Pages\Services\EmbedTypeRegistry;
use Modules\Pages\Support\ResolvedEmbed;

uses(RefreshDatabase::class);

test('the embed marker uses a typed element with validated attributes', function () {
    $parser = app(EmbedMarkerParser::class);

    $markers = $parser->parse('<p>before</p><cms-embed data-type="acme.video" data-ref="abc-123" data-variant="inline"></cms-embed><p>after</p>');

    expect($markers)->toHaveCount(1)
        ->and($markers[0]->type)->toBe('acme.video')
        ->and($markers[0]->reference)->toBe('abc-123')
        ->and($markers[0]->variant)->toBe('inline');

    expect(fn () => $parser->parse('<cms-embed data-ref="x"></cms-embed>'))->toThrow(PageOperationException::class)
        ->and(fn () => $parser->parse('<cms-embed data-type="acme.video" data-ref="../etc/passwd"></cms-embed>'))->toThrow(PageOperationException::class);
});

test('a registered embed type resolves a typed payload with a validated design variant', function () {
    $registry = app(EmbedTypeRegistry::class);
    $registry->register(new class implements EmbedType
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

    $resolved = app(EmbedResolver::class)->resolve('<cms-embed data-type="acme.video" data-ref="abc" data-variant="modal"></cms-embed>');

    expect($resolved)->toHaveCount(1)
        ->and($resolved[0])->toBeInstanceOf(ResolvedEmbed::class)
        ->and($resolved[0]->degraded)->toBeFalse()
        ->and($resolved[0]->variant)->toBe('modal')
        ->and($resolved[0]->payload)->toBe(['reference' => 'abc', 'kind' => 'video']);
});

test('an unknown embed type or unresolvable reference degrades without exposing internals', function () {
    $resolved = app(EmbedResolver::class)->resolve('<cms-embed data-type="unknown.type" data-ref="abc"></cms-embed>');

    expect($resolved[0]->degraded)->toBeTrue()
        ->and($resolved[0]->type)->toBe('unknown.type');

    $registry = app(EmbedTypeRegistry::class);
    $registry->register(new class implements EmbedType
    {
        public function identifier(): string
        {
            return 'acme.broken';
        }

        public function variants(): array
        {
            return ['default'];
        }

        public function defaultVariant(): string
        {
            return 'default';
        }

        public function resolve(string $reference): ?array
        {
            return null;
        }
    });

    $unresolvable = app(EmbedResolver::class)->resolve('<cms-embed data-type="acme.broken" data-ref="missing"></cms-embed>');

    expect($unresolvable[0]->degraded)->toBeTrue()
        ->and($unresolvable[0]->payload)->toBeNull();
});

test('an invalid variant falls back to the default variant', function () {
    $registry = app(EmbedTypeRegistry::class);
    $registry->register(new class implements EmbedType
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

    $resolved = app(EmbedResolver::class)->resolve('<cms-embed data-type="acme.video" data-ref="abc" data-variant="not-listed"></cms-embed>');

    expect($resolved[0]->variant)->toBe('inline');
});

test('the embed marker cannot select a Blade, class, route or import', function () {
    $parser = app(EmbedMarkerParser::class);

    expect(fn () => $parser->parse('<cms-embed data-type="../../secret" data-ref="x"></cms-embed>'))->toThrow(PageOperationException::class)
        ->and(fn () => $parser->parse('<cms-embed data-type="acme.video" data-ref="x" data-variant="../../etc/passwd"></cms-embed>'))->toThrow(PageOperationException::class);
});

test('the default embed presentation resolves against the Base template', function () {
    $base = CmsTemplate::query()->where('identifier', 'base')->sole();

    $resolution = app(TemplatePresentationResolver::class)->resolve(CmsPresentation::PublicEmbedDefault, $base);

    expect($resolution->presentationTemplate->identifier)->toBe('base');
});
