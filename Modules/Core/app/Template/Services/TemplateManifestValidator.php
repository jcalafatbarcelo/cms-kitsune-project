<?php

namespace Modules\Core\Template\Services;

use JsonException;
use Modules\Core\Localization\Services\UiCatalogRepository;
use Modules\Core\Template\Exceptions\TemplateOperationException;

class TemplateManifestValidator
{
    private const MAX_MANIFEST_BYTES = 65_536;

    public function __construct(private readonly string $root) {}

    /** @return array{directory: string, directory_key: string, identifier: string, name: string, manifest_hash: string} */
    public function inspect(string $directory): array
    {
        if (! preg_match('/^[A-Za-z][A-Za-z0-9]{0,99}$/D', $directory)) {
            throw new TemplateOperationException('Template directory is invalid.');
        }

        $templatePath = $this->root.DIRECTORY_SEPARATOR.$directory;
        $rootPath = realpath($this->root);
        $resolvedPath = realpath($templatePath);

        if (is_link($templatePath) || $rootPath === false || $resolvedPath === false || ! is_dir($resolvedPath)
            || dirname($resolvedPath) !== $rootPath) {
            throw new TemplateOperationException("Template directory [$directory] is unavailable or unsafe.");
        }

        $manifestPath = $resolvedPath.DIRECTORY_SEPARATOR.'template.json';

        if (is_link($manifestPath) || ! is_file($manifestPath)) {
            throw new TemplateOperationException("Template [$directory] must contain a regular template.json file.");
        }

        $bytes = file_get_contents($manifestPath, false, null, 0, self::MAX_MANIFEST_BYTES + 1);

        if ($bytes === false || strlen($bytes) > self::MAX_MANIFEST_BYTES) {
            throw new TemplateOperationException("Template manifest [$directory] could not be read or exceeds 64 KiB.");
        }

        try {
            $manifest = json_decode($bytes, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new TemplateOperationException("Template manifest [$directory] is not valid UTF-8 JSON.", previous: $exception);
        }

        $keys = is_array($manifest) ? array_keys($manifest) : [];
        sort($keys);

        if ($keys !== ['identifier', 'name', 'presentations', 'schema_version']
            || ($manifest['schema_version'] ?? null) !== 1
            || ! is_string($manifest['identifier'])
            || ! preg_match('/^[a-z][a-z0-9-]{0,99}$/D', $manifest['identifier'])
            || ! is_string($manifest['name'])
            || $manifest['name'] === ''
            || mb_strlen($manifest['name'], 'UTF-8') > 100
            || preg_match('/[\x00-\x1F\x7F]/', $manifest['name'])
            || ! is_array($manifest['presentations'])
            || count($manifest['presentations']) < 1
            || count($manifest['presentations']) > 100) {
            throw new TemplateOperationException("Template manifest [$directory] has an unsupported schema.");
        }

        $viewsPath = $resolvedPath.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'views';
        $this->assertSafeDirectory($resolvedPath.DIRECTORY_SEPARATOR.'Resources', $resolvedPath);
        $this->assertSafeDirectory($viewsPath, $resolvedPath);
        $resolvedViewsPath = realpath($viewsPath);

        if ($directory === 'Base' && $manifest['identifier'] !== 'base') {
            throw new TemplateOperationException('Base must use the [base] identifier.');
        }

        foreach ($manifest['presentations'] as $presentation) {
            if (! is_string($presentation)
                || ! preg_match('/^[a-z][a-z0-9]*(?:\.[a-z][a-z0-9]*)+$/D', $presentation)
                || count(array_keys($manifest['presentations'], $presentation, true)) !== 1) {
                throw new TemplateOperationException("Template [$directory] has an invalid presentation.");
            }

            $relativePath = str_replace('.', DIRECTORY_SEPARATOR, $presentation).'.blade.php';
            $viewPath = $viewsPath.DIRECTORY_SEPARATOR.$relativePath;
            $this->assertSafePathComponents($viewsPath, $relativePath);
            $resolvedViewPath = realpath($viewPath);

            if ($resolvedViewPath === false || ! is_file($resolvedViewPath)
                || ! str_starts_with($resolvedViewPath, $resolvedViewsPath.DIRECTORY_SEPARATOR)) {
                throw new TemplateOperationException("Template [$directory] is missing the Blade for [$presentation].");
            }
        }

        if (in_array('public.page.standard', $manifest['presentations'], true)) {
            $catalog = (new UiCatalogRepository(
                $resolvedPath.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'lang',
                $manifest['identifier'],
            ))->snapshot('en');
            foreach (['page.home.under-construction.heading', 'page.home.under-construction.message'] as $key) {
                if (! array_key_exists($manifest['identifier'].'::'.$key, $catalog->lines)) {
                    throw new TemplateOperationException("Template [$directory] is missing required UI key [$key].");
                }
            }
        }

        return [
            'directory' => $directory,
            'directory_key' => strtolower($directory),
            'identifier' => $manifest['identifier'],
            'name' => $manifest['name'],
            'manifest_hash' => hash('sha256', $bytes),
        ];
    }

    private function assertSafeDirectory(string $path, string $templatePath): void
    {
        $resolvedPath = realpath($path);

        if (is_link($path) || $resolvedPath === false || ! is_dir($resolvedPath)
            || ! str_starts_with($resolvedPath, $templatePath.DIRECTORY_SEPARATOR)) {
            throw new TemplateOperationException('Template views directory is unavailable or unsafe.');
        }
    }

    private function assertSafePathComponents(string $basePath, string $relativePath): void
    {
        $currentPath = $basePath;

        foreach (explode(DIRECTORY_SEPARATOR, $relativePath) as $component) {
            $currentPath .= DIRECTORY_SEPARATOR.$component;

            if (is_link($currentPath)) {
                throw new TemplateOperationException('Template Blade path contains a symbolic link.');
            }
        }
    }
}
