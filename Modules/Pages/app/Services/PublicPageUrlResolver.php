<?php

namespace Modules\Pages\Services;

use Modules\Core\Language\Models\Language;
use Modules\Core\Language\Models\LanguageSetting;
use Modules\Pages\Models\PageLanguageHome;
use Modules\Pages\Models\PageTranslation;

class PublicPageUrlResolver
{
    public function forPage(int $pageId, string $locale): ?string
    {
        $language = Language::query()->where('locale', $locale)->where('is_active', true)->first();
        if ($language === null) {
            return null;
        }

        $translation = PageTranslation::query()->with('page')->where('page_id', $pageId)->where('language_id', $language->id)->first();
        if ($translation === null || ! $this->isPublic($translation, $language->id)) {
            return null;
        }

        $default = LanguageSetting::query()->with('frontendDefaultLanguage')->findOrFail(1)->frontendDefaultLanguage;
        $prefix = $language->id === $default->id ? null : $this->canonicalPrefix($language);
        if (PageLanguageHome::query()->where('language_id', $language->id)->where('page_translation_id', $translation->id)->exists()) {
            return $prefix === null ? '/' : '/'.$prefix.'/';
        }

        $slugs = [];
        $page = $translation->page;
        while ($page !== null) {
            $slug = PageTranslation::query()->where('page_id', $page->id)->where('language_id', $language->id)->value('slug');
            if (! is_string($slug)) {
                return null;
            }
            array_unshift($slugs, $slug);
            $page = $page->parent;
        }

        return '/'.implode('/', array_filter([$prefix, ...$slugs]));
    }

    private function isPublic(PageTranslation $translation, int $languageId): bool
    {
        $page = $translation->page;
        while ($page !== null) {
            if (! $page->is_published || ! PageTranslation::query()->where('page_id', $page->id)->where('language_id', $languageId)->where('is_published', true)->exists()) {
                return false;
            }
            $page = $page->parent;
        }

        return true;
    }

    private function canonicalPrefix(Language $language): string
    {
        $family = substr($language->url_prefix, 0, 2);
        $active = Language::query()->where('is_active', true)->get()
            ->filter(fn (Language $candidate) => substr($candidate->url_prefix, 0, 2) === $family);

        return ($language->is_url_general || $active->count() === 1) ? $family : $language->url_prefix;
    }
}
