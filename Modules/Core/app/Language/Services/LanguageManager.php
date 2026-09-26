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
            if (Language::query()->where('url_prefix', $manifest['url_prefix'])->exists()) {
                throw new LanguageOperationException("URL prefix [{$manifest['url_prefix']}] is already installed.");
            }

            return Language::query()->create([
                'locale' => $manifest['locale'],
                'url_prefix' => $manifest['url_prefix'],
                'name' => $manifest['name'],
                'native_name' => $manifest['native_name'],
                'text_direction' => $manifest['text_direction'],
                'is_active' => false,
                'is_url_general' => $manifest['is_url_general'],
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

            $this->ensureFamilyCanActivate($language);

            $language->update(['is_active' => true]);

            return $language->refresh();
        }, attempts: 5);
    }

    public function setUrlGeneral(string $locale): Language
    {
        UiCatalogRepository::assertLocale($locale);

        return DB::transaction(function () use ($locale) {
            LanguageSetting::query()->lockForUpdate()->findOrFail(1);
            $language = $this->lockedLanguage($locale);
            if (! $language->is_active) {
                throw new LanguageOperationException("Inactive language [$locale] cannot be URL general.");
            }

            $family = $this->family($language->url_prefix);
            $generals = Language::query()->where('is_active', true)->where('is_url_general', true)->lockForUpdate()->get()
                ->filter(fn (Language $candidate) => $this->family($candidate->url_prefix) === $family);
            if ($language->url_prefix === $family && $generals->contains(fn (Language $candidate) => $candidate->url_prefix !== $family)) {
                throw new LanguageOperationException("Short URL prefix [$family] conflicts with an existing regional general language.");
            }

            $generals->each->update(['is_url_general' => false]);
            $language->update(['is_url_general' => true]);

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
            $family = $this->family($language->url_prefix);
            $familyActive = Language::query()->where('is_active', true)->lockForUpdate()->get()
                ->filter(fn (Language $candidate) => $this->family($candidate->url_prefix) === $family);
            if ($language->is_url_general && $familyActive->count() > 1) {
                throw new LanguageOperationException("URL general language [$locale] cannot be disabled while its family has multiple active variants.");
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

    /** @return array{schema_version: int, locale: string, url_prefix: string, is_url_general: bool, name: string, native_name: string, text_direction: string, catalogs: array{core: true}} */
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

        $expectedKeys = ['catalogs', 'is_url_general', 'locale', 'name', 'native_name', 'schema_version', 'text_direction', 'url_prefix'];
        $actualKeys = is_array($manifest) ? array_keys($manifest) : [];
        sort($actualKeys);

        if ($actualKeys !== $expectedKeys
            || ($manifest['schema_version'] ?? null) !== 2
            || ($manifest['catalogs'] ?? null) !== ['core' => true]) {
            throw new LanguageOperationException('The manifest schema is not supported.');
        }

        if (! is_string($manifest['locale'])) {
            throw new LanguageOperationException('Manifest field [locale] is invalid.');
        }

        UiCatalogRepository::assertLocale($manifest['locale']);
        if (! is_string($manifest['url_prefix']) || preg_match('/^[a-z]{2}(?:-[a-z]{2})?$/D', $manifest['url_prefix']) !== 1) {
            throw new LanguageOperationException('Manifest field [url_prefix] must be xx or xx-xx.');
        }
        if (! is_bool($manifest['is_url_general'])) {
            throw new LanguageOperationException('Manifest field [is_url_general] must be boolean.');
        }

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

    private function ensureFamilyCanActivate(Language $language): void
    {
        $family = $this->family($language->url_prefix);
        $active = Language::query()->where('is_active', true)->lockForUpdate()->get()
            ->filter(fn (Language $candidate) => $this->family($candidate->url_prefix) === $family);
        if ($active->isNotEmpty() && ! $active->contains('is_url_general', true)) {
            throw new LanguageOperationException("A URL general language must be set for family [$family] before activating another variant.");
        }
        if ($language->is_url_general && $active->contains('is_url_general', true)) {
            throw new LanguageOperationException("Family [$family] already has a URL general language.");
        }
    }

    private function family(string $prefix): string
    {
        return substr($prefix, 0, 2);
    }
}
