<?php

namespace Modules\Pages\Services;

use Modules\Pages\Contracts\ContentEditor;
use Modules\Pages\Exceptions\PageOperationException;

class ContentEditorRegistry
{
    /** @var array<string, ContentEditor> */
    private array $editors = [];

    public function register(ContentEditor $editor): void
    {
        $identifier = $editor->identifier();
        if (! preg_match('/^[a-z][a-z0-9-]*$/D', $identifier) || strlen($identifier) > 100) {
            throw new PageOperationException("Content editor [$identifier] has an invalid identifier.");
        }
        if (isset($this->editors[$identifier])) {
            throw new PageOperationException("Content editor [$identifier] is already registered.");
        }

        $this->editors[$identifier] = $editor;
    }

    public function has(string $identifier): bool
    {
        return isset($this->editors[$identifier]);
    }

    public function get(string $identifier): ContentEditor
    {
        if (! isset($this->editors[$identifier])) {
            throw new PageOperationException("Content editor [$identifier] is not available.");
        }

        return $this->editors[$identifier];
    }
}
