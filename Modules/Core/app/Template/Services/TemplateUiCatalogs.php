<?php

namespace Modules\Core\Template\Services;

use Modules\Core\Localization\Services\UiCatalogRepository;
use Modules\Core\Localization\Services\UiCatalogResolver;
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

    public function text(CmsTemplate $template, string $key, string $locale): string
    {
        if (! preg_match('/^[a-z][a-z0-9-]*(?:\.[a-z][a-z0-9-]*)+$/D', $key)) {
            throw new TemplateOperationException('Template UI key is invalid.');
        }

        return (new UiCatalogResolver(
            new UiCatalogRepository($this->catalogPath($template), $template->identifier),
            (string) config('core.ui_catalog_fallback_mode'),
            $this->logger,
            $template->identifier,
        ))->get($template->identifier.'::'.$key, $locale);
    }

    public function validateStandard(CmsTemplate $template): void
    {
        $snapshot = (new UiCatalogRepository($this->catalogPath($template), $template->identifier))->snapshot('en');

        foreach (self::STANDARD_KEYS as $key) {
            if (! array_key_exists($template->identifier.'::'.$key, $snapshot->lines)) {
                throw new TemplateOperationException("Template [$template->identifier] is missing required UI key [$key].");
            }
        }
    }

    private function catalogPath(CmsTemplate $template): string
    {
        return $this->root.DIRECTORY_SEPARATOR.$template->directory.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'lang';
    }
}
