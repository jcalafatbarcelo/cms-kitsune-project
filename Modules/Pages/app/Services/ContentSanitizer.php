<?php

namespace Modules\Pages\Services;

use Modules\Pages\Contracts\ContentType;
use Modules\Pages\Exceptions\PageOperationException;

class ContentSanitizer
{
    public function __construct(private readonly ContentEditorRegistry $editors) {}

    public function sanitize(ContentType $type, string $html): string
    {
        $editor = $type->editor();
        if ($editor === null || ! $this->editors->has($editor)) {
            throw new PageOperationException("No server sanitizer is available for content type [{$type->identifier()}].");
        }

        return $this->editors->get($editor)->sanitize($html, $type->policy());
    }
}
