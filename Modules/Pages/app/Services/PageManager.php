<?php

namespace Modules\Pages\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Core\Language\Models\Language;
use Modules\Core\Template\Models\CmsTemplate;
use Modules\Core\Template\Models\CmsTemplateSetting;
use Modules\Core\Template\Services\TemplateUiCatalogs;
use Modules\Pages\Exceptions\PageOperationException;
use Modules\Pages\Models\Page;
use Modules\Pages\Models\PageLanguageHome;
use Modules\Pages\Models\PageTranslation;

class PageManager
{
    public function __construct(private readonly TemplateUiCatalogs $templateUi) {}

    public function create(string $locale, string $slug, string $title, ?int $parentId = null, ?string $templateIdentifier = null): PageTranslation
    {
        try {
            return DB::transaction(function () use ($locale, $slug, $title, $parentId, $templateIdentifier) {
                $language = $this->language($locale);
                $this->validateText($slug, $title);
                if ($parentId !== null && Page::query()->lockForUpdate()->find($parentId) === null) {
                    throw new PageOperationException("Parent page [$parentId] is not available.");
                }
                $this->ensureSlugIsAvailable($language->id, $slug);
                $home = PageLanguageHome::query()->lockForUpdate()->find($language->id);
                $template = $this->template($templateIdentifier);
                $page = Page::query()->create([
                    'parent_id' => $parentId,
                    'uses_explicit_template' => $templateIdentifier !== null,
                    'explicit_template_id' => $templateIdentifier === null ? null : $template->id,
                    'presentation_key' => 'public.page.standard',
                    'is_published' => $home === null,
                ]);
                $translation = PageTranslation::query()->create([
                    'page_id' => $page->id,
                    'language_id' => $language->id,
                    'title' => $title,
                    'slug' => $slug,
                    'is_published' => $home === null,
                ]);
                if ($home === null) {
                    $this->setHomeLocked($language, $translation);
                }

                return $translation;
            }, attempts: 5);
        } catch (QueryException $exception) {
            $this->throwExpectedConstraintError($exception);
        }
    }

    public function translate(int $pageId, string $locale, string $slug, string $title): PageTranslation
    {
        try {
            return DB::transaction(function () use ($pageId, $locale, $slug, $title) {
                $language = $this->language($locale);
                $this->validateText($slug, $title);
                $page = Page::query()->lockForUpdate()->find($pageId);
                if ($page === null) {
                    throw new PageOperationException("Page [$pageId] is not available.");
                }
                if (PageTranslation::query()->where('page_id', $page->id)->where('language_id', $language->id)->lockForUpdate()->exists()) {
                    throw new PageOperationException("Page [$pageId] already has a translation for [$locale].");
                }
                $this->ensureSlugIsAvailable($language->id, $slug);
                $home = PageLanguageHome::query()->lockForUpdate()->find($language->id);
                $translation = PageTranslation::query()->create(['page_id' => $page->id, 'language_id' => $language->id, 'title' => $title, 'slug' => $slug, 'is_published' => $home === null]);
                if ($home === null) {
                    $page->update(['is_published' => true]);
                    $this->setHomeLocked($language, $translation);
                }

                return $translation;
            }, attempts: 5);
        } catch (QueryException $exception) {
            $this->throwExpectedConstraintError($exception);
        }
    }

    public function publish(int $pageId, string $locale): void
    {
        DB::transaction(function () use ($pageId, $locale) {
            $translation = $this->translation($pageId, $locale);
            $translation->page->update(['is_published' => true]);
            $translation->update(['is_published' => true]);
        }, attempts: 5);
    }

    public function unpublish(int $pageId, string $locale): void
    {
        DB::transaction(function () use ($pageId, $locale) {
            $translation = $this->translation($pageId, $locale);
            $language = $this->language($locale);
            $home = PageLanguageHome::query()->lockForUpdate()->find($language->id);
            $homeTranslation = $home === null ? null : PageTranslation::query()->with('page')->find($home->page_translation_id);
            if ($homeTranslation !== null && $this->isAncestorOf($translation->page_id, $homeTranslation->page)) {
                throw new PageOperationException('The home page cannot be unpublished without a replacement.');
            }
            $translation->update(['is_published' => false]);
        }, attempts: 5);
    }

    public function setHome(int $pageId, string $locale): void
    {
        DB::transaction(function () use ($pageId, $locale) {
            $language = $this->language($locale);
            $translation = $this->translation($pageId, $locale);
            if (! $translation->is_published || ! $translation->page->is_published || ! $language->is_active) {
                throw new PageOperationException('The home page must be publicly available.');
            }
            $this->setHomeLocked($language, $translation);
        }, attempts: 5);
    }

    /** @return array{translation: PageTranslation, template: CmsTemplate} */
    public function home(string $locale): array
    {
        $language = Language::query()->where('locale', $locale)->where('is_active', true)->first();
        if ($language === null) {
            throw new PageOperationException('The requested language is unavailable.');
        }
        $home = PageLanguageHome::query()->find($language->id);
        $translation = $home === null ? null : PageTranslation::query()->with('page')->find($home->page_translation_id);
        if ($translation === null || ! $this->isPublic($translation, $language->id)) {
            throw new PageOperationException('No public home page is available.');
        }
        $template = $this->templateFor($translation->page);
        $this->templateUi->validateStandard($template);

        return ['translation' => $translation, 'template' => $template];
    }

    private function setHomeLocked(Language $language, PageTranslation $translation): void
    {
        PageLanguageHome::query()->updateOrCreate(['language_id' => $language->id], ['page_translation_id' => $translation->id]);
    }

    private function language(string $locale): Language
    {
        $language = Language::query()->where('locale', $locale)->lockForUpdate()->first();
        if ($language === null) {
            throw new PageOperationException("Language [$locale] is not installed.");
        }

        return $language;
    }

    private function translation(int $pageId, string $locale): PageTranslation
    {
        $language = $this->language($locale);
        $translation = PageTranslation::query()->with('page')->where('page_id', $pageId)->where('language_id', $language->id)->lockForUpdate()->first();
        if ($translation === null) {
            throw new PageOperationException("Page [$pageId] has no translation for [$locale].");
        }

        return $translation;
    }

    private function template(?string $identifier): CmsTemplate
    {
        if ($identifier === null) {
            $identifier = CmsTemplateSetting::query()->findOrFail(1)->defaultTemplate->identifier;
        }
        $template = CmsTemplate::query()->where('identifier', $identifier)->where('is_active', true)->first();
        if ($template === null) {
            throw new PageOperationException("Template [$identifier] is not active.");
        }
        $this->templateUi->validateStandard($template);

        return $template;
    }

    private function templateFor(Page $page): CmsTemplate
    {
        if (! $page->uses_explicit_template) {
            return $this->template(null);
        }

        $template = CmsTemplate::query()->find($page->explicit_template_id);
        if ($template === null) {
            throw new PageOperationException("Explicit template [{$page->explicit_template_id}] is not available.");
        }

        return $this->template($template->identifier);
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

    private function isAncestorOf(int $pageId, Page $page): bool
    {
        while (true) {
            if ($page->id === $pageId) {
                return true;
            }
            if ($page->parent === null) {
                return false;
            }
            $page = $page->parent;
        }
    }

    private function ensureSlugIsAvailable(int $languageId, string $slug): void
    {
        if (PageTranslation::query()->where('language_id', $languageId)->where('slug', $slug)->lockForUpdate()->exists()) {
            throw new PageOperationException("Slug [$slug] is already in use for this language.");
        }
    }

    private function throwExpectedConstraintError(QueryException $exception): never
    {
        if (str_starts_with((string) $exception->getCode(), '23')) {
            throw new PageOperationException('The page data conflicts with an existing Page or translation.', previous: $exception);
        }

        throw $exception;
    }

    private function validateText(string $slug, string $title): void
    {
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) || strlen($slug) > 100
            || $title === '' || mb_strlen($title, 'UTF-8') > 255 || preg_match('/[\x00-\x1F\x7F]/', $title)) {
            throw new PageOperationException('The page title or slug is invalid.');
        }
    }
}
