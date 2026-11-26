<?php

namespace Modules\Core\Localization\Services;

use Illuminate\Support\Facades\Cache;
use Psr\Log\LoggerInterface;
use Throwable;

class UiCatalogOverrides
{
    private const CACHE_PREFIX = 'cms.ui-catalog-overrides';

    private const CACHE_TTL_SECONDS = 300;

    private const INVALID_OWNER = '__invalid__';

    /** @var array{enabled: bool, version: int}|null */
    private ?array $state = null;

    private bool $stateLoaded = false;

    private bool $storeUnavailable = false;

    /** @var array<string, list<array{key: string, value: string}>> */
    private array $rows = [];

    private ?int $rowsVersion = null;

    /** @var array<string, array{version: int, fingerprint: string, lines: array<string, string>}> */
    private array $memory = [];

    /** @var array<string, true> */
    private array $warned = [];

    public function __construct(
        private readonly UiCatalogOverrideStore $store,
        private readonly UiCatalogOverrideValidator $validator,
        private readonly LoggerInterface $logger,
    ) {}

    public function line(string $key, string $locale): ?string
    {
        return $this->lines($locale)[$key] ?? null;
    }

    public function forget(): void
    {
        $this->state = null;
        $this->stateLoaded = false;
        $this->storeUnavailable = false;
        $this->rows = [];
        $this->rowsVersion = null;
        $this->memory = [];
        $this->validator->forget();
    }

    /** @return array<string, string> */
    private function lines(string $locale): array
    {
        $state = $this->state();

        if ($state === null || ! $state['enabled']) {
            return [];
        }

        $version = $state['version'];
        $rows = $this->rowsForLocale($locale, $version);

        if ($rows === null) {
            return [];
        }

        $fingerprint = $this->fingerprint($rows);

        if (isset($this->memory[$locale])
            && $this->memory[$locale]['version'] === $version
            && $this->memory[$locale]['fingerprint'] === $fingerprint) {
            return $this->memory[$locale]['lines'];
        }

        $cacheKey = self::CACHE_PREFIX.':'.$version.':'.$locale.':'.$fingerprint;
        $cached = $this->readCache($cacheKey);

        if ($cached !== null) {
            return $this->remember($locale, $version, $fingerprint, $cached);
        }

        $lines = [];

        foreach ($rows as $row) {
            $reason = $this->validator->inspect($row['key'], $row['value']);

            if ($reason !== null) {
                $this->warnInvalid($row['key'], $locale, $reason);

                continue;
            }

            $lines[$row['key']] = $row['value'];
        }

        $this->writeCache($cacheKey, $lines);

        return $this->remember($locale, $version, $fingerprint, $lines);
    }

    /** @return array{enabled: bool, version: int}|null */
    private function state(): ?array
    {
        if ($this->storeUnavailable) {
            return null;
        }

        if ($this->stateLoaded) {
            return $this->state;
        }

        $this->stateLoaded = true;
        $this->state = $this->store->state();

        if ($this->state === null) {
            $this->storeUnavailable = true;
        }

        return $this->state;
    }

    /** @return list<array{key: string, value: string}>|null */
    private function rowsForLocale(string $locale, int $version): ?array
    {
        if ($this->storeUnavailable) {
            return null;
        }

        if ($this->rowsVersion !== $version) {
            $this->rows = [];
            $this->rowsVersion = $version;
        }

        if (array_key_exists($locale, $this->rows)) {
            return $this->rows[$locale];
        }

        $rows = $this->store->rowsForLocale($locale);

        if ($rows === null) {
            $this->storeUnavailable = true;

            return null;
        }

        return $this->rows[$locale] = $rows;
    }

    /** @param list<array{key: string, value: string}> $rows */
    private function fingerprint(array $rows): string
    {
        $owners = [];

        foreach ($rows as $row) {
            $owners[$this->validator->ownerOf($row['key']) ?? self::INVALID_OWNER] = true;
        }

        ksort($owners);
        $parts = [];

        foreach (array_keys($owners) as $owner) {
            $hash = $owner === self::INVALID_OWNER
                ? 'invalid'
                : ($this->validator->ownerHash($owner) ?? 'unavailable');
            $parts[] = $owner.'='.$hash;
        }

        return hash('sha256', implode('|', $parts));
    }

    /** @param array<string, string> $lines
     * @return array<string, string> */
    private function remember(string $locale, int $version, string $fingerprint, array $lines): array
    {
        $this->memory[$locale] = [
            'version' => $version,
            'fingerprint' => $fingerprint,
            'lines' => $lines,
        ];

        return $lines;
    }

    /** @return array<string, string>|null */
    private function readCache(string $cacheKey): ?array
    {
        try {
            $cached = Cache::get($cacheKey);
        } catch (Throwable) {
            $this->warnCache();

            return null;
        }

        if ($cached === null) {
            return null;
        }

        if (! is_array($cached)) {
            $this->warnMalformedCache();

            return null;
        }

        foreach ($cached as $key => $value) {
            if (! is_string($key) || ! is_string($value)) {
                $this->warnMalformedCache();

                return null;
            }
        }

        return $cached;
    }

    /** @param array<string, string> $lines */
    private function writeCache(string $cacheKey, array $lines): void
    {
        try {
            Cache::put($cacheKey, $lines, now()->addSeconds(self::CACHE_TTL_SECONDS));
        } catch (Throwable) {
            $this->warnCache();
        }
    }

    private function warnCache(): void
    {
        if (isset($this->warned['cache'])) {
            return;
        }

        $this->warned['cache'] = true;
        $this->logger->warning('UI catalog override cache is unavailable.');
    }

    private function warnMalformedCache(): void
    {
        if (isset($this->warned['cache_malformed'])) {
            return;
        }

        $this->warned['cache_malformed'] = true;
        $this->logger->warning('A cached UI catalog override snapshot was ignored.');
    }

    private function warnInvalid(string $key, string $locale, string $reason): void
    {
        $reportKey = $locale.'|'.$key.'|'.$reason;

        if (isset($this->warned[$reportKey])) {
            return;
        }

        $this->warned[$reportKey] = true;
        $this->logger->warning('A UI catalog override was ignored.', [
            'key' => $key,
            'locale' => $locale,
            'owner' => $this->validator->ownerOf($key),
            'reason' => $reason,
        ]);
    }
}
