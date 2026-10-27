<?php

namespace Modules\RichText;

use Modules\Pages\Contracts\ContentType;
use Modules\Pages\Contracts\SanitizationPolicy;
use Modules\Pages\Support\SanitizationPolicyValue;

final class RichTextType implements ContentType
{
    public const IDENTIFIER = 'rich-text';

    public const CONTENT_KEY = 'body.content';

    public function identifier(): string
    {
        return self::IDENTIFIER;
    }

    public function name(): string
    {
        return 'Rich text';
    }

    public function editor(): ?string
    {
        return 'tinymce';
    }

    public function contentKey(): ?string
    {
        return self::CONTENT_KEY;
    }

    public function isSystem(): bool
    {
        return false;
    }

    public function policy(): SanitizationPolicy
    {
        return new SanitizationPolicyValue([
            'p' => [], 'br' => [], 'hr' => [],
            'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
            'ul' => [], 'ol' => [], 'li' => [],
            'blockquote' => [], 'pre' => [], 'code' => [],
            'div' => [], 'section' => [], 'article' => [],
            'table' => [], 'thead' => [], 'tbody' => [], 'tfoot' => [], 'tr' => [], 'th' => [], 'td' => [], 'caption' => [],
            'strong' => [], 'em' => [], 'b' => [], 'i' => [], 'u' => [], 's' => [], 'del' => [], 'ins' => [],
            'mark' => [], 'sub' => [], 'sup' => [], 'small' => [], 'q' => [], 'cite' => [], 'abbr' => [], 'span' => [],
            'a' => ['href', 'title', 'rel'],
            'cms-embed' => ['data-type', 'data-ref', 'data-variant'],
        ], ['http', 'https', 'mailto'], true);
    }
}
