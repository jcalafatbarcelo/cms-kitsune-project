<?php

namespace Modules\Pages\Services;

use DOMDocument;
use Modules\Pages\Exceptions\PageOperationException;
use Modules\Pages\Support\EmbedMarker;

class EmbedMarkerParser
{
    /** @return list<EmbedMarker> */
    public function parse(string $html): array
    {
        if ($html === '') {
            return [];
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><main>'.$html.'</main>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $markers = [];
        foreach ($document->getElementsByTagName('cms-embed') as $node) {
            $type = $node->getAttribute('data-type');
            $reference = $node->getAttribute('data-ref');
            $variant = $node->getAttribute('data-variant');
            if (! $this->validType($type) || ! $this->validReference($reference) || ! $this->validVariant($variant)) {
                throw new PageOperationException('The embed marker is invalid.');
            }
            $markers[] = new EmbedMarker($type, $reference, $variant === '' ? null : $variant);
        }

        return $markers;
    }

    public function normalize(string $html): string
    {
        if ($html === '' || ! str_contains($html, '<cms-embed')) {
            return $html;
        }

        return preg_replace_callback(
            '/<cms-embed\b([^>]*?)>(?!\s*<\/cms-embed>)/is',
            fn (array $matches) => '<cms-embed '.trim($matches[1], " \t\n\r\0\x0B/").'></cms-embed>',
            $html,
        );
    }

    private function validType(string $type): bool
    {
        return preg_match('/^[a-z][a-z0-9]*(?:\.[a-z][a-z0-9]*)*$/D', $type) === 1 && strlen($type) <= 100;
    }

    private function validReference(string $reference): bool
    {
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,254}$/D', $reference) === 1;
    }

    private function validVariant(string $variant): bool
    {
        return $variant === '' || (preg_match('/^[a-z][a-z0-9-]*$/D', $variant) === 1 && strlen($variant) <= 100);
    }
}
