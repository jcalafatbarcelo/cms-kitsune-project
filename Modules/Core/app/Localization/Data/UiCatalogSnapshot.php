<?php

namespace Modules\Core\Localization\Data;

final readonly class UiCatalogSnapshot
{
    /**
     * @param  array<string, string>  $lines
     */
    public function __construct(
        public string $locale,
        public array $lines,
        public string $baseHash,
        public string $localizedHash,
    ) {}
}
