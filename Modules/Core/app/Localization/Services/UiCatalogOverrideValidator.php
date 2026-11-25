<?php

namespace Modules\Core\Localization\Services;

use Modules\Core\Language\Models\Language;
use Modules\Core\Localization\Exceptions\CatalogValidationException;
use Modules\Core\Localization\Exceptions\UiCatalogOverrideException;
use Throwable;

class UiCatalogOverrideValidator
{
    private const MAX_KEY_BYTES = 191;

    public function __construct(private readonly UiCatalogOwnerResolver $owners) {}

    public function assertLocale(string $locale): void
    {
        UiCatalogRepository::assertLocale($locale);

        try {
            $installed = Language::query()->where('locale', $locale)->exists();
        } catch (Throwable $exception) {
            throw new UiCatalogOverrideException('Installed languages are unavailable.', previous: $exception);
        }

        if (! $installed) {
            throw new UiCatalogOverrideException("Locale [$locale] is not installed.");
        }
    }

    /** @return array{owner: string, key: string} */
    public function parseKey(string $key): array
    {
        if (strlen($key) > self::MAX_KEY_BYTES
            || preg_match('/^([a-z][a-z0-9-]*)::/D', $key, $matches) !== 1) {
            throw new UiCatalogOverrideException('The UI catalog override key is invalid.');
        }

        try {
            UiCatalogRepository::assertKey($key, $matches[1]);
        } catch (CatalogValidationException) {
            throw new UiCatalogOverrideException('The UI catalog override key is invalid.');
        }

        return ['owner' => $matches[1], 'key' => $key];
    }

    public function ownerOf(string $key): ?string
    {
        try {
            return $this->parseKey($key)['owner'];
        } catch (UiCatalogOverrideException) {
            return null;
        }
    }

    public function ownerHash(string $owner): ?string
    {
        return $this->owners->baseHash($owner);
    }

    public function forget(): void
    {
        $this->owners->forget();
    }

    public function assertRemovableKey(string $key): void
    {
        if ($key === ''
            || strlen($key) > self::MAX_KEY_BYTES
            || ! mb_check_encoding($key, 'UTF-8')
            || UiCatalogValueValidator::containsUnsafeCharacters($key)) {
            throw new UiCatalogOverrideException('The UI catalog override key cannot be removed.');
        }
    }

    public function assertWritable(string $key, string $value): void
    {
        $owner = $this->parseKey($key)['owner'];
        $lines = $this->owners->baseLines($owner);

        if ($lines === null) {
            throw new UiCatalogOverrideException("The UI catalog owner [$owner] is not resolvable.");
        }

        try {
            UiCatalogValueValidator::assertValue($key, $value);
        } catch (CatalogValidationException $exception) {
            throw new UiCatalogOverrideException("The override value for [$key] is invalid.", previous: $exception);
        }

        if (! array_key_exists($key, $lines)) {
            throw new UiCatalogOverrideException("The UI catalog key [$key] does not exist in the owner's en catalog.");
        }

        try {
            UiCatalogValueValidator::assertCompatible($key, $lines[$key], $value);
        } catch (CatalogValidationException $exception) {
            throw new UiCatalogOverrideException($exception->getMessage(), previous: $exception);
        }
    }

    public function inspect(string $key, string $value): ?string
    {
        try {
            $owner = $this->parseKey($key)['owner'];
        } catch (UiCatalogOverrideException) {
            return 'key_format';
        }

        $lines = $this->owners->baseLines($owner);

        if ($lines === null) {
            return 'owner';
        }

        if (! array_key_exists($key, $lines)) {
            return 'key';
        }

        try {
            UiCatalogValueValidator::assertValue($key, $value);
        } catch (CatalogValidationException) {
            return 'value';
        }

        try {
            UiCatalogValueValidator::assertCompatible($key, $lines[$key], $value);
        } catch (CatalogValidationException) {
            return 'compatibility';
        }

        return null;
    }
}
