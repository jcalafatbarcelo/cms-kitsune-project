<?php

namespace Modules\Pages\Services;

use Modules\Pages\Support\EmbedMarker;
use Modules\Pages\Support\ResolvedEmbed;

class EmbedResolver
{
    public function __construct(
        private readonly EmbedTypeRegistry $registry,
        private readonly EmbedMarkerParser $parser,
    ) {}

    /** @return list<ResolvedEmbed> */
    public function resolve(string $html): array
    {
        return array_map(
            fn (EmbedMarker $marker) => $this->resolveMarker($marker),
            $this->parser->parse($html),
        );
    }

    private function resolveMarker(EmbedMarker $marker): ResolvedEmbed
    {
        if (! $this->registry->has($marker->type)) {
            return ResolvedEmbed::degraded($marker->type, $marker->variant ?? 'default');
        }

        $type = $this->registry->get($marker->type);
        $variant = $marker->variant !== null && in_array($marker->variant, $type->variants(), true)
            ? $marker->variant
            : $type->defaultVariant();
        $payload = $type->resolve($marker->reference);
        if ($payload === null) {
            return ResolvedEmbed::degraded($type->identifier(), $variant);
        }

        return new ResolvedEmbed($type->identifier(), $variant, $payload, false);
    }
}
