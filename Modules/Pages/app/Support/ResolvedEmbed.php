<?php

namespace Modules\Pages\Support;

final readonly class ResolvedEmbed
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function __construct(
        public string $type,
        public string $variant,
        public ?array $payload,
        public bool $degraded,
    ) {}

    /** @return array<string, mixed> */
    public static function degraded(string $type, string $variant): self
    {
        return new self($type, $variant, null, true);
    }
}
