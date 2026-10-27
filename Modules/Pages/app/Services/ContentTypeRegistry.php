<?php

namespace Modules\Pages\Services;

use Modules\Pages\Contracts\ContentType;
use Modules\Pages\Exceptions\PageOperationException;

class ContentTypeRegistry
{
    /** @var array<string, ContentType> */
    private array $types = [];

    public function register(ContentType $type): void
    {
        $identifier = $type->identifier();
        if (! preg_match('/^[a-z][a-z0-9-]*(?:\.[a-z][a-z0-9-]*)*$/D', $identifier) || strlen($identifier) > 100) {
            throw new PageOperationException("Content type [$identifier] has an invalid identifier.");
        }
        if (isset($this->types[$identifier])) {
            throw new PageOperationException("Content type [$identifier] is already registered.");
        }

        $this->types[$identifier] = $type;
    }

    public function get(string $identifier): ContentType
    {
        if (! isset($this->types[$identifier])) {
            throw new PageOperationException("Content type [$identifier] is not available.");
        }

        return $this->types[$identifier];
    }

    public function has(string $identifier): bool
    {
        return isset($this->types[$identifier]);
    }

    /** @return array<string, ContentType> */
    public function all(): array
    {
        return $this->types;
    }

    /** @return array<string, ContentType> */
    public function selectable(): array
    {
        return array_filter($this->types, fn (ContentType $type) => ! $type->isSystem());
    }
}
