<?php

namespace App\Services\Admin;

use App\Support\Diagnostics\ErrorDetails;
use Illuminate\Http\Response;
use Modules\Core\Language\Models\LanguageSetting;
use Modules\Core\Localization\Exceptions\CatalogValidationException;
use Modules\Core\Template\Enums\CmsPresentation;
use Modules\Core\Template\Exceptions\TemplateOperationException;
use Modules\Core\Template\Models\CmsTemplate;
use Modules\Core\Template\Services\TemplatePresentationResolver;
use Modules\Core\Template\Services\TemplateUiCatalogs;

class AdminPresentation
{
    public function __construct(private TemplatePresentationResolver $presentations, private TemplateUiCatalogs $catalogs) {}

    public function render(CmsPresentation $key, int $status = 200): Response
    {
        try {
            $base = CmsTemplate::query()->where('identifier', 'base')->first();
            if ($base === null) {
                abort(503);
            }
            $presentation = $this->presentations->resolve($key, $base);
            $locale = LanguageSetting::query()->findOrFail(1)->backofficeDefaultLanguage->locale;
            $text = fn (string $key): string => $this->catalogs->text($base, $base, $key, $locale);
            $errorDetails = ErrorDetails::enabled();

            return response()->view($presentation->viewName, compact('locale', 'text', 'errorDetails'), $status)
                ->header('Cache-Control', 'private, no-store');
        } catch (TemplateOperationException|CatalogValidationException) {
            abort(503);
        }
    }
}
