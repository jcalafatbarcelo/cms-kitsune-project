<?php

namespace Modules\Core\Template\Services;

use Illuminate\Support\Facades\Blade;
use Modules\Core\Template\Data\PresentationResolution;
use Modules\Core\Template\Enums\CmsPresentation;
use Modules\Core\Template\Exceptions\TemplateOperationException;
use Modules\Core\Template\Models\CmsTemplate;

class TemplatePresentationResolver
{
    private readonly TemplateManifestValidator $validator;

    public function __construct(private readonly string $root)
    {
        $this->validator = new TemplateManifestValidator($root);
    }

    public function resolve(CmsPresentation $presentation, CmsTemplate $effectiveTemplate): PresentationResolution
    {
        $effectiveTemplate = CmsTemplate::query()->find($effectiveTemplate->id);
        $baseTemplate = CmsTemplate::query()->where('identifier', 'base')->first();

        if ($effectiveTemplate === null || $baseTemplate === null) {
            throw new TemplateOperationException('A required template configuration is unavailable.');
        }

        $effective = $this->snapshot($effectiveTemplate);
        $base = $effectiveTemplate->id === $baseTemplate->id ? $effective : $this->snapshot($baseTemplate);
        $source = in_array($presentation->value, $effective['presentations'], true)
            ? [$effectiveTemplate, $effective]
            : (in_array($presentation->value, $base['presentations'], true) ? [$baseTemplate, $base] : null);

        if ($source === null) {
            throw new TemplateOperationException('A required template presentation is unavailable.');
        }

        [$presentationTemplate, $snapshot] = $source;
        $namespace = 'cms-template-'.$presentationTemplate->identifier;
        app('view')->addNamespace($namespace, $snapshot['views_path']);
        Blade::anonymousComponentPath($snapshot['views_path'], $namespace);

        return new PresentationResolution(
            $effectiveTemplate,
            $presentationTemplate,
            $namespace.'::'.$presentation->value,
        );
    }

    /** @return array{identifier: string, manifest_hash: string, presentations: array<int, string>, views_path: string} */
    private function snapshot(CmsTemplate $template): array
    {
        $snapshot = $this->validator->presentationSnapshot($template->directory);

        if ($snapshot['identifier'] !== $template->identifier || $snapshot['manifest_hash'] !== $template->manifest_hash) {
            throw new TemplateOperationException('A required template configuration is unavailable.');
        }

        return $snapshot;
    }
}
