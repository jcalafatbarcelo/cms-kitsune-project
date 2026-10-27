<?php

namespace Modules\Pages\Contracts;

interface SanitizationPolicy
{
    /** @return array<string, array<int, string>> element => allowed attributes */
    public function allowedElements(): array;

    /** @return list<string> */
    public function allowedLinkSchemes(): array;

    public function allowRelativeLinks(): bool;
}
