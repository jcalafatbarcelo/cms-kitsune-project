<?php

namespace Modules\Pages\Support;

final readonly class EmbedMarker
{
    public function __construct(
        public string $type,
        public string $reference,
        public ?string $variant,
    ) {}
}
