<?php

namespace Modules\TinyMCE\Services;

use Modules\Pages\Contracts\ContentEditor;
use Modules\Pages\Contracts\SanitizationPolicy;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

final class TinymceEditor implements ContentEditor
{
    public function identifier(): string
    {
        return 'tinymce';
    }

    public function sanitize(string $html, SanitizationPolicy $policy): string
    {
        $config = new HtmlSanitizerConfig;
        foreach ($policy->allowedElements() as $element => $attributes) {
            $config = $config->allowElement($element, $attributes);
        }
        $config = $config->allowLinkSchemes($policy->allowedLinkSchemes());
        $config = $config->allowRelativeLinks($policy->allowRelativeLinks());

        return (new HtmlSanitizer($config))->sanitize($html);
    }
}
