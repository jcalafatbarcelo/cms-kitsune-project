<?php

namespace Modules\Pages\Services;

use Modules\Pages\Contracts\EmbedType;
use Modules\Pages\Exceptions\PageOperationException;

class EmbedTypeRegistry
{
    /** @var array<string, EmbedType> */
    private array $types = [];

    public function register(EmbedType $type): void
    {
        $identifier = $type->identifier();
        if (! preg_match('/^[a-z][a-z0-9]*(?:\.[a-z][a-z0-9]*)*$/D', $identifier) || strlen($identifier) > 100) {
            throw new PageOperationException("Embed type [$identifier] has an invalid identifier.");
        }
        if (isset($this->types[$identifier])) {
            throw new PageOperationException("Embed type [$identifier] is already registered.");
        }

        $this->types[$identifier] = $type;
    }

    public function has(string $identifier): bool
    {
        return isset($this->types[$identifier]);
    }

    public function get(string $identifier): EmbedType
    {
        if (! isset($this->types[$identifier])) {
            throw new PageOperationException("Embed type [$identifier] is not available.");
        }

        return $this->types[$identifier];
    }
}
