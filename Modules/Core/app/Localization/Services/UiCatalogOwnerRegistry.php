<?php

namespace Modules\Core\Localization\Services;

use Illuminate\Support\Str;
use LogicException;

class UiCatalogOwnerRegistry
{
    /** @var array<string, string> */
    private array $owners = [];

    public function register(string $origin, bool $reserved = false): string
    {
        $normalized = Str::kebab($origin);

        if ($normalized === '' || (! $reserved && $normalized === 'core')) {
            throw new LogicException("UI catalog owner [$origin] is reserved or invalid.");
        }

        if (isset($this->owners[$normalized])) {
            throw new LogicException(
                "UI catalog owner [$origin] collides with [{$this->owners[$normalized]}].",
            );
        }

        $this->owners[$normalized] = $origin;

        return $normalized;
    }
}
