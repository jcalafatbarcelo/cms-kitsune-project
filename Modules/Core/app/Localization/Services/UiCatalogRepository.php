<?php

namespace Modules\Core\Localization\Services;

use JsonException;
use Modules\Core\Localization\Data\UiCatalogSnapshot;
use Modules\Core\Localization\Exceptions\CatalogValidationException;

class UiCatalogRepository
{
    private const MAX_CATALOG_BYTES = 2 * 1024 * 1024;

    private const MAX_KEYS = 10_000;

    private const MAX_KEY_BYTES = 191;

    private const MAX_VALUE_BYTES = 16_384;

    /** @var array<string, UiCatalogSnapshot> */
    private array $cache = [];

    private ?string $validatedBaseHash = null;

    public function __construct(private readonly string $catalogDirectory) {}

    public function snapshot(string $locale): UiCatalogSnapshot
    {
        $this->assertLocale($locale);

        [$baseLines, $baseHash] = $this->read('en');

        if ($this->validatedBaseHash !== $baseHash) {
            $this->validateLines($baseLines);
            $this->validatedBaseHash = $baseHash;
        }

        if ($locale === 'en') {
            return new UiCatalogSnapshot('en', $baseLines, $baseHash, $baseHash);
        }

        [$localizedLines, $localizedHash] = $this->read($locale);
        $cacheKey = $locale.':'.$baseHash.':'.$localizedHash;

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $this->validateLines($localizedLines, $baseLines);

        return $this->cache[$cacheKey] = new UiCatalogSnapshot(
            $locale,
            $localizedLines,
            $baseHash,
            $localizedHash,
        );
    }

    public static function assertLocale(string $locale): void
    {
        if (! preg_match('/^[a-z]{2,3}(?:_[A-Z][a-z]{3})?(?:_(?:[A-Z]{2}|[0-9]{3}))?$/D', $locale)) {
            throw new CatalogValidationException('The locale is not a valid CMS locale.');
        }
    }

    public static function assertKey(string $key): void
    {
        if (strlen($key) > self::MAX_KEY_BYTES
            || ! preg_match('/^core::[a-z0-9]+(?:[._-][a-z0-9]+)*$/D', $key)) {
            throw new CatalogValidationException('A UI catalog key is invalid.');
        }
    }

    /**
     * @return array{array<string, string>, string}
     */
    private function read(string $locale): array
    {
        $root = realpath($this->catalogDirectory);
        $path = $this->catalogDirectory.DIRECTORY_SEPARATOR.$locale.'.json';

        if ($root === false || is_link($path) || ! is_file($path)) {
            throw new CatalogValidationException("UI catalog [$locale] is not a regular file.");
        }

        $canonicalPath = realpath($path);

        if ($canonicalPath === false || ! $this->isWithin($canonicalPath, $root)) {
            throw new CatalogValidationException("UI catalog [$locale] is outside its allowed directory.");
        }

        $bytes = file_get_contents($canonicalPath, false, null, 0, self::MAX_CATALOG_BYTES + 1);

        if ($bytes === false) {
            throw new CatalogValidationException("UI catalog [$locale] could not be read.");
        }

        if (strlen($bytes) > self::MAX_CATALOG_BYTES) {
            throw new CatalogValidationException("UI catalog [$locale] exceeds 2 MiB.");
        }

        try {
            $lines = json_decode($bytes, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new CatalogValidationException(
                "UI catalog [$locale] is not valid UTF-8 JSON.",
                previous: $exception,
            );
        }

        if (! is_array($lines) || array_is_list($lines)) {
            throw new CatalogValidationException("UI catalog [$locale] must be a JSON object.");
        }

        return [$lines, hash('sha256', $bytes)];
    }

    /**
     * @param  array<mixed>  $lines
     * @param  array<string, string>|null  $baseLines
     */
    private function validateLines(array $lines, ?array $baseLines = null): void
    {
        if ($lines === [] || count($lines) > self::MAX_KEYS) {
            throw new CatalogValidationException('A UI catalog must contain between 1 and 10,000 keys.');
        }

        foreach ($lines as $key => $value) {
            if (! is_string($key)) {
                throw new CatalogValidationException('A UI catalog key is invalid.');
            }

            self::assertKey($key);

            if (! is_string($value)
                || $value === ''
                || strlen($value) > self::MAX_VALUE_BYTES
                || str_contains($value, '<')
                || str_contains($value, '>')
                || preg_match('/[\x00-\x1F\x7F]/', $value)) {
                throw new CatalogValidationException("UI catalog value [$key] is invalid.");
            }

            $this->validatePluralization($key, $value);
        }

        if ($baseLines === null) {
            return;
        }

        $missing = array_diff_key($baseLines, $lines);
        $unknown = array_diff_key($lines, $baseLines);

        if ($missing !== [] || $unknown !== []) {
            throw new CatalogValidationException('The localized UI catalog must exactly match its base keys.');
        }

        foreach ($baseLines as $key => $baseValue) {
            $localizedValue = $lines[$key];

            if ($this->placeholders($baseValue) !== $this->placeholders($localizedValue)) {
                throw new CatalogValidationException("UI catalog placeholders differ for [$key].");
            }

            if (str_contains($baseValue, '|') !== str_contains($localizedValue, '|')) {
                throw new CatalogValidationException("UI catalog pluralization differs for [$key].");
            }
        }
    }

    /** @return list<string> */
    private function placeholders(string $value): array
    {
        preg_match_all('/:([A-Za-z][A-Za-z0-9_]*)/', $value, $matches);
        $placeholders = array_values(array_unique(array_map('strtolower', $matches[1])));
        sort($placeholders);

        return $placeholders;
    }

    private function validatePluralization(string $key, string $value): void
    {
        if (! str_contains($value, '|')) {
            return;
        }

        if (! in_array('count', $this->placeholders($value), true)) {
            throw new CatalogValidationException("Pluralized UI catalog value [$key] requires :count.");
        }

        foreach (explode('|', $value) as $segment) {
            $segment = trim($segment);

            if ($segment === '' || (($segment[0] === '{' || $segment[0] === '[')
                && ! preg_match('/^[{[][-?\d*,.]+[}\]]\s*.+$/sD', $segment))) {
                throw new CatalogValidationException("UI catalog pluralization is invalid for [$key].");
            }
        }
    }

    private function isWithin(string $path, string $root): bool
    {
        $prefix = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        return DIRECTORY_SEPARATOR === '\\'
            ? str_starts_with(strtolower($path), strtolower($prefix))
            : str_starts_with($path, $prefix);
    }
}
