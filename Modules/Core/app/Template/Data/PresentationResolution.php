<?php

namespace Modules\Core\Template\Data;

use Modules\Core\Template\Models\CmsTemplate;

class PresentationResolution
{
    public function __construct(
        public readonly CmsTemplate $effectiveTemplate,
        public readonly CmsTemplate $presentationTemplate,
        public readonly string $viewName,
    ) {}
}
