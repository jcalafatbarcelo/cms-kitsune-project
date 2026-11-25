<?php

namespace Modules\Core\Localization\Services;

use Illuminate\Database\QueryException;
use Modules\Core\Localization\Exceptions\CatalogValidationException;
use Modules\Core\Template\Models\CmsTemplate;

class UiCatalogOwnerResolver
{
    /**
     * @var array<string, array{signature: string, snapshot: array{hash: string, lines: array<string, string>}}>
     */
    private array $snapshots = [];

    /** @var array<string, string|null> */
    private array $directories = [];

    public function __construct(
        private readonly string $coreCatalogPath,
        private readonly string $templateRoot,
    ) {}

    /**
     * Resolve the current `en` catalog lines and hash of an override owner.
     *
     * The snapshot is reused while the catalog file metadata is unchanged. This
     * keeps fingerprinting cheap without preventing a new request or forget()
     * from observing a deployed catalog change.
     *
     * @return array{hash: string, lines: array<string, string>}|null
     */
    public function snapshot(string $owner): ?array
    {
        $path = $this->catalogPath($owner);

        if ($path === null) {
            unset($this->snapshots[$owner]);

            return null;
        }

        $signature = $this->signature($path.DIRECTORY_SEPARATOR.'en.json');

        if ($signature === null) {
            unset($this->snapshots[$owner]);

            return null;
        }

        if (isset($this->snapshots[$owner]) && $this->snapshots[$owner]['signature'] === $signature) {
            return $this->snapshots[$owner]['snapshot'];
        }

        $snapshot = $this->readSnapshot($path, $owner);

        if ($snapshot === null) {
            unset($this->snapshots[$owner]);

            return null;
        }

        $this->snapshots[$owner] = ['signature' => $signature, 'snapshot' => $snapshot];

        return $snapshot;
    }

    /** @return array{hash: string, lines: array<string, string>}|null */
    protected function readSnapshot(string $path, string $owner): ?array
    {
        try {
            $snapshot = (new UiCatalogRepository($path, $owner))->snapshot('en');
        } catch (CatalogValidationException) {
            return null;
        }

        return ['hash' => $snapshot->baseHash, 'lines' => $snapshot->lines];
    }

    /** @return array<string, string>|null */
    public function baseLines(string $owner): ?array
    {
        return $this->snapshot($owner)['lines'] ?? null;
    }

    public function baseHash(string $owner): ?string
    {
        return $this->snapshot($owner)['hash'] ?? null;
    }

    public function forget(): void
    {
        $this->snapshots = [];
        $this->directories = [];
    }

    private function catalogPath(string $owner): ?string
    {
        if ($owner === 'core') {
            return $this->coreCatalogPath;
        }

        if (array_key_exists($owner, $this->directories)) {
            $directory = $this->directories[$owner];

            return $directory === null ? null : $this->templatePath($directory);
        }

        try {
            $template = CmsTemplate::query()->where('identifier', $owner)->first();
        } catch (QueryException) {
            return null;
        }

        $this->directories[$owner] = $template?->directory;

        return $template === null ? null : $this->templatePath($template->directory);
    }

    private function templatePath(string $directory): string
    {
        return $this->templateRoot.DIRECTORY_SEPARATOR
            .$directory.DIRECTORY_SEPARATOR
            .'Resources'.DIRECTORY_SEPARATOR.'lang';
    }

    private function signature(string $file): ?string
    {
        clearstatcache(true, $file);

        if (is_link($file) || ! is_file($file)) {
            return null;
        }

        $stat = @stat($file);

        return $stat === false ? null : $stat['mtime'].':'.$stat['size'];
    }
}
