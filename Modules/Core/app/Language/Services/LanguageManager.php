<?php

namespace Modules\Core\Language\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use JsonException;
use Modules\Core\Language\Exceptions\LanguageOperationException;
use Modules\Core\Language\Models\Language;
use Modules\Core\Language\Models\LanguageSetting;
use Modules\Core\Localization\Services\UiCatalogRepository;

class LanguageManager
{
    private const MAX_MANIFEST_BYTES = 65_536;

    public function __construct(private readonly UiCatalogRepository $catalogs) {}

    /** @return Collection<int, Language> */
    public function all(): Collection
    {
        return Language::query()->orderBy('locale')->get();
    }

    public function install(string $manifestPath): Language
    {
        $manifest = $this->readManifest($manifestPath);
        $this->catalogs->snapshot($manifest['locale']);

        return DB::transaction(function () use ($manifest) {
            LanguageSetting::query()->lockForUpdate()->findOrFail(1);

            if (Language::query()->where('locale', $manifest['locale'])->exists()) {
                throw new LanguageOperationException("Language [{$manifest['locale']}] is already installed.");
            }

            return Language::query()->create([
                'locale' => $manifest['locale'],
                'name' => $manifest['name'],
                'native_name' => $manifest['native_name'],
                'text_direction' => $manifest['text_direction'],
                'is_active' => false,
                'installed_at' => now(),
            ]);
        }, attempts: 5);
    }

    public function activate(string $locale): Language
    {
        UiCatalogRepository::assertLocale($locale);

        return DB::transaction(function () use ($locale) {
            LanguageSetting::query()->lockForUpdate()->findOrFail(1);
            $language = $this->lockedLanguage($locale);

            if ($language->is_active) {
                throw new LanguageOperationException("Language [$locale] is already active.");
            }

            $language->update(['is_active' => true]);

            return $language->refresh();
        }, attempts: 5);
    }

    public function disable(string $locale): Language
    {
        UiCatalogRepository::assertLocale($locale);

        return DB::transaction(function () use ($locale) {
            $settings = LanguageSetting::query()->lockForUpdate()->findOrFail(1);
            $language = $this->lockedLanguage($locale);

            if (! $language->is_active) {
                throw new LanguageOperationException("Language [$locale] is already inactive.");
            }

            if (in_array($language->id, [
                $settings->frontend_default_language_id,
                $settings->backoffice_default_language_id,
            ], true)) {
                throw new LanguageOperationException("Default language [$locale] cannot be disabled.");
            }

            if (Language::query()->where('is_active', true)->lockForUpdate()->get()->count() <= 1) {
                throw new LanguageOperationException('At least one language must remain active.');
            }

            $language->update(['is_active' => false]);

            return $language->refresh();
        }, attempts: 5);
    }

    public function setDefault(string $context, string $locale): LanguageSetting
    {
        if (! in_array($context, ['frontend', 'backoffice'], true)) {
            throw new LanguageOperationException('Context must be [frontend] or [backoffice].');
        }

        UiCatalogRepository::assertLocale($locale);

        return DB::transaction(function () use ($context, $locale) {
            $settings = LanguageSetting::query()->lockForUpdate()->findOrFail(1);
            $language = $this->lockedLanguage($locale);

            if (! $language->is_active) {
                throw new LanguageOperationException("Inactive language [$locale] cannot be a default.");
            }

            $settings->update([$context.'_default_language_id' => $language->id]);

            return $settings->refresh();
        }, attempts: 5);
    }

    /** @return array<string, string> locale => SHA-256 */
    public function validate(?string $locale = null): array
    {
        $locales = $locale === null
            ? Language::query()->orderBy('locale')->pluck('locale')->all()
            : [$this->lockedLanguage($locale)->locale];

        $hashes = [];

        foreach ($locales as $installedLocale) {
            $hashes[$installedLocale] = $this->catalogs->snapshot($installedLocale)->localizedHash;
        }

        return $hashes;
    }

    private function lockedLanguage(string $locale): Language
    {
        $language = Language::query()->where('locale', $locale)->lockForUpdate()->first();

        if ($language === null) {
            throw new LanguageOperationException("Language [$locale] is not installed.");
        }

        return $language;
    }

    /** @return array{schema_version: int, locale: string, name: string, native_name: string, text_direction: string, catalogs: array{core: true}} */
    private function readManifest(string $path): array
    {
        if (is_link($path) || ! is_file($path)) {
            throw new LanguageOperationException('The manifest must be a regular JSON file.');
        }

        $bytes = file_get_contents($path, false, null, 0, self::MAX_MANIFEST_BYTES + 1);

        if ($bytes === false || strlen($bytes) > self::MAX_MANIFEST_BYTES) {
            throw new LanguageOperationException('The manifest could not be read or exceeds 64 KiB.');
        }

        try {
            $manifest = json_decode($bytes, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new LanguageOperationException('The manifest is not valid UTF-8 JSON.', previous: $exception);
        }

        $expectedKeys = ['catalogs', 'locale', 'name', 'native_name', 'schema_version', 'text_direction'];
        $actualKeys = is_array($manifest) ? array_keys($manifest) : [];
        sort($actualKeys);

        if ($actualKeys !== $expectedKeys
            || ($manifest['schema_version'] ?? null) !== 1
            || ($manifest['catalogs'] ?? null) !== ['core' => true]) {
            throw new LanguageOperationException('The manifest schema is not supported.');
        }

        if (! is_string($manifest['locale'])) {
            throw new LanguageOperationException('Manifest field [locale] is invalid.');
        }

        UiCatalogRepository::assertLocale($manifest['locale']);

        foreach (['name', 'native_name'] as $field) {
            if (! is_string($manifest[$field])
                || $manifest[$field] === ''
                || strlen($manifest[$field]) > 100
                || preg_match('/[\x00-\x1F\x7F]/', $manifest[$field])) {
                throw new LanguageOperationException("Manifest field [$field] is invalid.");
            }
        }

        if (! is_string($manifest['text_direction'])
            || ! in_array($manifest['text_direction'], ['ltr', 'rtl'], true)) {
            throw new LanguageOperationException('Manifest text_direction must be [ltr] or [rtl].');
        }

        return $manifest;
    }
}
