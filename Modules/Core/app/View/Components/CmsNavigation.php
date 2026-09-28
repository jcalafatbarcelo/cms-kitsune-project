<?php

namespace Modules\Core\View\Components;

use Illuminate\View\Component;
use Modules\Core\Template\Enums\CmsPresentation;
use Modules\Core\Template\Exceptions\TemplateOperationException;
use Modules\Core\Template\Models\CmsTemplate;
use Modules\Core\Template\Services\TemplatePresentationResolver;
use Modules\Core\Template\Services\TemplateUiCatalogs;
use Modules\Navigation\Services\PublicMenuResolver;

class CmsNavigation extends Component
{
    public function __construct(
        public readonly string $identifier,
        public readonly string $locale,
        public readonly CmsTemplate $effectiveTemplate,
    ) {}

    public function render(): \Closure
    {
        return function (array $data) {
            try {
                $resolution = app(TemplatePresentationResolver::class)->resolve(CmsPresentation::PublicNavigationMenu, $this->effectiveTemplate);
                $items = app(PublicMenuResolver::class)->forMenu($this->identifier, $this->locale);

                return view($resolution->viewName, [
                    ...$data,
                    'items' => $items,
                    'identifier' => $this->identifier,
                    'locale' => $this->locale,
                    'effectiveTemplate' => $resolution->effectiveTemplate,
                    'presentationTemplate' => $resolution->presentationTemplate,
                    'templateUi' => app(TemplateUiCatalogs::class),
                ])->render();
            } catch (TemplateOperationException) {
                abort(503);
            }
        };
    }
}
