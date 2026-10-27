<?php

namespace Modules\Pages\Contracts;

interface ContentType
{
    public function identifier(): string;

    public function name(): string;

    public function editor(): ?string;

    public function contentKey(): ?string;

    public function isSystem(): bool;

    public function policy(): SanitizationPolicy;
}
