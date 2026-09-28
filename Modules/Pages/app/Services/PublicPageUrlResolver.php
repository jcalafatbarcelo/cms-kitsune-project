<?php

namespace Modules\Pages\Services;

use Modules\Core\Language\Models\Language;
use Modules\Core\Language\Models\LanguageSetting;
use Modules\Pages\Models\Page;
use Modules\Pages\Models\PageLanguageHome;
use Modules\Pages\Models\PageTranslation;

class PublicPageUrlResolver
{
    public function forPage(int $pageId, string $locale): ?string
    {
        return $this->forPages([$pageId], $locale)[$pageId] ?? null;
    }

    /** @return array<int, string> */
    public function forPages(array $pageIds, string $locale): array
    {
        $pageIds = array_values(array_unique(array_filter($pageIds, fn (mixed $pageId) => is_int($pageId) && $pageId > 0)));
        if ($pageIds === []) {
            return [];
        }

        $activeLanguages = Language::query()->where('is_active', true)->get();
        $language = $activeLanguages->firstWhere('locale', $locale);
        if ($language === null) {
            return [];
        }

        $pages = Page::query()->whereIn('id', $pageIds)->get()->keyBy('id');
        $parentIds = $pages->pluck('parent_id')->filter()->unique()->all();
        while ($parentIds !== []) {
            $ancestors = Page::query()->whereIn('id', $parentIds)->get()->keyBy('id');
            $pages = $pages->union($ancestors);
            $parentIds = $ancestors->pluck('parent_id')->filter(fn (?int $parentId) => $parentId !== null && ! $pages->has($parentId))->unique()->all();
        }

        $default = LanguageSetting::query()->with('frontendDefaultLanguage')->findOrFail(1)->frontendDefaultLanguage;
        $prefix = $language->id === $default->id ? null : $this->canonicalPrefix($language, $activeLanguages);
        $homes = PageLanguageHome::query()->where('language_id', $language->id)->get()->keyBy('page_translation_id');
        $translations = PageTranslation::query()->whereIn('page_id', $pages->keys())->where('language_id', $language->id)->get()->keyBy('page_id');
        $urls = [];

        foreach ($pageIds as $pageId) {
            $translation = $translations->get($pageId);
            if ($translation === null || ! $this->isPublic($pages, $translations, $translation->page_id)) {
                continue;
            }
            if ($homes->has($translation->id)) {
                $urls[$pageId] = $prefix === null ? '/' : '/'.$prefix.'/';

                continue;
            }

            $slugs = [];
            $page = $pages->get($pageId);
            while ($page !== null) {
                $ancestorTranslation = $translations->get($page->id);
                if ($ancestorTranslation === null) {
                    continue 2;
                }
                array_unshift($slugs, $ancestorTranslation->slug);
                $page = $page->parent_id === null ? null : $pages->get($page->parent_id);
            }
            $urls[$pageId] = '/'.implode('/', array_filter([$prefix, ...$slugs]));
        }

        return $urls;
    }

    private function isPublic($pages, $translations, int $pageId): bool
    {
        $page = $pages->get($pageId);
        while ($page !== null) {
            $translation = $translations->get($page->id);
            if (! $page->is_published || $translation === null || ! $translation->is_published) {
                return false;
            }
            $page = $page->parent_id === null ? null : $pages->get($page->parent_id);
        }

        return true;
    }

    private function canonicalPrefix(Language $language, $activeLanguages): string
    {
        $family = substr($language->url_prefix, 0, 2);
        $active = $activeLanguages->filter(fn (Language $candidate) => substr($candidate->url_prefix, 0, 2) === $family);

        return ($language->is_url_general || $active->count() === 1) ? $family : $language->url_prefix;
    }
}
