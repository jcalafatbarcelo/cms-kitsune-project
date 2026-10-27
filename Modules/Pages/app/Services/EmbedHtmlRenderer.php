<?php

namespace Modules\Pages\Services;

use Illuminate\View\View;
use Modules\Core\Template\Enums\CmsPresentation;
use Modules\Core\Template\Exceptions\TemplateOperationException;
use Modules\Core\Template\Models\CmsTemplate;
use Modules\Core\Template\Services\TemplatePresentationResolver;
use Modules\Core\Template\Services\TemplateUiCatalogs;
use Modules\Pages\Exceptions\PageOperationException;
use Modules\Pages\Support\ResolvedEmbed;

class EmbedHtmlRenderer
{
    public function __construct(
        private readonly EmbedResolver $resolver,
        private readonly EmbedMarkerParser $parser,
        private readonly TemplatePresentationResolver $presentations,
        private readonly TemplateUiCatalogs $templateUi,
    ) {}

    public function render(string $html, CmsTemplate $effectiveTemplate, string $locale): string
    {
        if ($html === '' || ! str_contains($html, '<cms-embed')) {
            return $html;
        }

        $html = $this->parser->normalize($html);

        try {
            $resolved = $this->resolver->resolve($html);
        } catch (PageOperationException) {
            return $this->substitute($html, []);
        }

        $rendered = [];
        foreach ($resolved as $embed) {
            try {
                $rendered[] = $this->embedView($embed, $effectiveTemplate, $locale)->render();
            } catch (TemplateOperationException) {
                abort(503);
            }
        }

        return $this->substitute($html, $rendered);
    }

    public function embedView(ResolvedEmbed $embed, CmsTemplate $effectiveTemplate, string $locale): View
    {
        $resolution = $this->presentations->resolve(CmsPresentation::PublicEmbedDefault, $effectiveTemplate);

        return view($resolution->viewName, [
            'embed' => $embed,
            'locale' => $locale,
            'effectiveTemplate' => $resolution->effectiveTemplate,
            'presentationTemplate' => $resolution->presentationTemplate,
            'templateUi' => $this->templateUi,
        ]);
    }

    /** @param list<string> $rendered */
    private function substitute(string $html, array $rendered): string
    {
        $index = 0;

        return preg_replace_callback(
            '/<cms-embed\b[^>]*>.*?<\/cms-embed>/is',
            fn () => $rendered[$index++] ?? '',
            $html,
        );
    }
}
