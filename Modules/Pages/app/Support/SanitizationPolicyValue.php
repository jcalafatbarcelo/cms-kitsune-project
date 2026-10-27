<?php

namespace Modules\Pages\Support;

use Modules\Pages\Contracts\SanitizationPolicy;

final readonly class SanitizationPolicyValue implements SanitizationPolicy
{
    /**
     * @param  array<string, array<int, string>>  $allowedElements  element => allowed attributes
     * @param  list<string>  $allowedLinkSchemes
     */
    public function __construct(
        private array $allowedElements,
        private array $allowedLinkSchemes = ['http', 'https', 'mailto'],
        private bool $allowRelativeLinks = true,
    ) {}

    public function allowedElements(): array
    {
        return $this->allowedElements;
    }

    public function allowedLinkSchemes(): array
    {
        return $this->allowedLinkSchemes;
    }

    public function allowRelativeLinks(): bool
    {
        return $this->allowRelativeLinks;
    }
}
