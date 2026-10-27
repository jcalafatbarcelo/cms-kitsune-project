<?php

namespace Modules\Pages\Contracts;

interface EmbedType
{
    public function identifier(): string;

    /** @return list<string> */
    public function variants(): array;

    public function defaultVariant(): string;

    /** @return array<string, mixed>|null typed payload, or null when the reference is unresolvable */
    public function resolve(string $reference): ?array;
}
