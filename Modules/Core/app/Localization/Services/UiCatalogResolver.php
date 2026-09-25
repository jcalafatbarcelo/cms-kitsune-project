<?php

namespace Modules\Core\Localization\Services;

use Illuminate\Translation\MessageSelector;
use InvalidArgumentException;
use Modules\Core\Localization\Exceptions\CatalogValidationException;
use Psr\Log\LoggerInterface;

class UiCatalogResolver
{
    /** @var array<string, true> */
    private array $reportedMissingKeys = [];

    public function __construct(
        private readonly UiCatalogRepository $repository,
        private readonly string $fallbackMode,
        private readonly LoggerInterface $logger,
        private readonly string $owner = 'core',
    ) {
        if (! in_array($fallbackMode, ['base', 'key'], true)) {
            throw new InvalidArgumentException(
                'CMS_UI_CATALOG_FALLBACK_MODE must be either [base] or [key].',
            );
        }
    }

    /** @param array<string, scalar|null> $replace */
    public function get(string $key, string $locale, array $replace = []): string
    {
        UiCatalogRepository::assertKey($key, $this->owner);
        UiCatalogRepository::assertLocale($locale);

        $line = $this->line($key, $locale);

        if ($line === null && $this->fallbackMode === 'base' && $locale !== 'en') {
            $line = $this->line($key, 'en');
        }

        if ($line === null) {
            $this->reportMissing($key, $locale);

            return $key;
        }

        return $this->replace($line, $replace);
    }

    /** @param array<string, scalar|null> $replace */
    public function choice(string $key, int|float $number, string $locale, array $replace = []): string
    {
        $line = $this->get($key, $locale);

        if ($line === $key) {
            return $key;
        }

        $selected = (new MessageSelector)->choose($line, $number, $this->pluralLocale($locale));

        return $this->replace($selected, ['count' => $number, ...$replace]);
    }

    private function line(string $key, string $locale): ?string
    {
        try {
            return $this->repository->snapshot($locale)->lines[$key] ?? null;
        } catch (CatalogValidationException $exception) {
            $reportKey = 'catalog:'.$locale.':'.$exception->getMessage();

            if (! isset($this->reportedMissingKeys[$reportKey])) {
                $this->reportedMissingKeys[$reportKey] = true;
                $this->logger->warning('UI catalog could not be loaded.', [
                    'locale' => $locale,
                    'owner' => $this->owner,
                    'reason' => $exception->getMessage(),
                ]);
            }

            return null;
        }
    }

    private function pluralLocale(string $locale): string
    {
        return explode('_', $locale, 2)[0];
    }

    /** @param array<string, scalar|null> $replace */
    private function replace(string $line, array $replace): string
    {
        $replacements = [];

        foreach ($replace as $key => $value) {
            $string = (string) ($value ?? '');
            $replacements[':'.$key] = $string;
            $replacements[':'.ucfirst($key)] = ucfirst($string);
            $replacements[':'.strtoupper($key)] = strtoupper($string);
        }

        return strtr($line, $replacements);
    }

    private function reportMissing(string $key, string $locale): void
    {
        $reportKey = $locale.':'.$key;

        if (isset($this->reportedMissingKeys[$reportKey])) {
            return;
        }

        $this->reportedMissingKeys[$reportKey] = true;
        $this->logger->warning('UI catalog key is missing.', [
            'key' => $key,
            'locale' => $locale,
            'owner' => str_contains($key, '::') ? strstr($key, '::', true) : 'unknown',
        ]);
    }
}
