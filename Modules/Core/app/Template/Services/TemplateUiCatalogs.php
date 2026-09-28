<?php

namespace Modules\Core\Template\Services;

use Modules\Core\Localization\Services\UiCatalogRepository;
use Modules\Core\Template\Exceptions\TemplateOperationException;
use Modules\Core\Template\Models\CmsTemplate;
use Psr\Log\LoggerInterface;

class TemplateUiCatalogs
{
    private const STANDARD_KEYS = [
        'page.home.under-construction.heading',
        'page.home.under-construction.message',
    ];

    public function __construct(private readonly string $root, private readonly LoggerInterface $logger) {}

    public function text(CmsTemplate $effectiveTemplate, CmsTemplate $presentationTemplate, string $key, string $locale): string
    {
        if (! preg_match('/^[a-z][a-z0-9-]*(?:\.[a-z][a-z0-9-]*)+$/D', $key)) {
            throw new TemplateOperationException('Template UI key is invalid.');
        }

        $templates = $effectiveTemplate->id === $presentationTemplate->id
            ? [$effectiveTemplate]
            : [$effectiveTemplate, $presentationTemplate];
        $locales = (string) config('core.ui_catalog_fallback_mode') === 'base' && $locale !== 'en'
            ? [$locale, 'en']
            : [$locale];

        foreach ($templates as $template) {
            foreach ($locales as $candidateLocale) {
                $line = $this->line($template, $key, $candidateLocale);
                if ($line !== null) {
                    return $line;
                }
            }
        }

        $this->logger->warning('UI catalog key is missing.', ['key' => $key, 'locale' => $locale]);

        return $key;
    }

    public function validateStandard(CmsTemplate $effectiveTemplate, CmsTemplate $presentationTemplate): void
    {
        foreach (self::STANDARD_KEYS as $key) {
            $this->text($effectiveTemplate, $presentationTemplate, $key, 'en');
        }
    }

    private function line(CmsTemplate $template, string $key, string $locale): ?string
    {
        $path = $this->catalogPath($template);
        $catalog = $path.DIRECTORY_SEPARATOR.$locale.'.json';
        $baseCatalog = $path.DIRECTORY_SEPARATOR.'en.json';
        if (! is_dir($path)) {
            return null;
        }

        if (! is_file($baseCatalog) && ! is_link($baseCatalog)) {
            (new UiCatalogRepository($path, $template->identifier))->snapshot('en');
        }

        if ($locale !== 'en' && ! is_file($catalog) && ! is_link($catalog)) {
            return null;
        }

        return (new UiCatalogRepository($path, $template->identifier))
            ->snapshot($locale)->lines[$template->identifier.'::'.$key] ?? null;
    }

    private function catalogPath(CmsTemplate $template): string
    {
        return $this->root.DIRECTORY_SEPARATOR.$template->directory.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'lang';
    }
}
