<?php

namespace Modules\Pages\Support;

use Modules\Pages\Contracts\ContentType;
use Modules\Pages\Contracts\SanitizationPolicy;

final readonly class ContentTypeDefinition implements ContentType
{
    public function __construct(
        private string $identifier,
        private string $name,
        private ?string $editor,
        private bool $isSystem,
        private SanitizationPolicy $policy,
        private ?string $contentKey = null,
    ) {}

    public function identifier(): string
    {
        return $this->identifier;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function editor(): ?string
    {
        return $this->editor;
    }

    public function contentKey(): ?string
    {
        return $this->contentKey;
    }

    public function isSystem(): bool
    {
        return $this->isSystem;
    }

    public function policy(): SanitizationPolicy
    {
        return $this->policy;
    }
}
