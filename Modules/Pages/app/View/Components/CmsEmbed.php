<?php

namespace Modules\Pages\View\Components;

use Illuminate\View\Component;
use Modules\Core\Template\Exceptions\TemplateOperationException;
use Modules\Core\Template\Models\CmsTemplate;
use Modules\Pages\Services\EmbedHtmlRenderer;
use Modules\Pages\Support\ResolvedEmbed;

class CmsEmbed extends Component
{
    public function __construct(
        public readonly ResolvedEmbed $embed,
        public readonly CmsTemplate $effectiveTemplate,
        public readonly string $locale,
    ) {}

    public function render(): \Closure
    {
        return function () {
            try {
                return app(EmbedHtmlRenderer::class)->embedView($this->embed, $this->effectiveTemplate, $this->locale);
            } catch (TemplateOperationException) {
                abort(503);
            }
        };
    }
}
