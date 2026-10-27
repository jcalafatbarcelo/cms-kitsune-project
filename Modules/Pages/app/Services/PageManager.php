<?php

namespace Modules\Pages\Services;

use App\Services\Admin\AdminAuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Core\Language\Models\Language;
use Modules\Core\Language\Models\LanguageSetting;
use Modules\Core\Template\Data\PresentationResolution;
use Modules\Core\Template\Enums\CmsPresentation;
use Modules\Core\Template\Models\CmsTemplate;
use Modules\Core\Template\Models\CmsTemplateSetting;
use Modules\Core\Template\Services\TemplatePresentationResolver;
use Modules\Core\Template\Services\TemplateUiCatalogs;
use Modules\Pages\Contracts\ContentType;
use Modules\Pages\Contracts\HomePageLocator;
use Modules\Pages\Contracts\MenuReferenceResolver;
use Modules\Pages\Exceptions\PageOperationException;
use Modules\Pages\Models\Page;
use Modules\Pages\Models\PageTranslation;
use Modules\Pages\Models\PageTranslationContent;

class PageManager
{
    private const MAX_CONTENT_BYTES = 4 * 1024 * 1024;

    public function __construct(
        private readonly TemplateUiCatalogs $templateUi,
        private readonly TemplatePresentationResolver $presentations,
        private readonly AdminAuditLogger $audit,
        private readonly ContentTypeRegistry $types,
        private readonly ContentSanitizer $sanitizer,
        private readonly EmbedMarkerParser $embedMarkers,
        private readonly HomePageLocator $homes,
        private readonly MenuReferenceResolver $menuReferences,
    ) {}

    public function create(string $locale, string $slug, string $title, ?int $parentId = null, ?string $templateIdentifier = null, ?string $contentType = null): PageTranslation
    {
        try {
            return DB::transaction(function () use ($locale, $slug, $title, $parentId, $templateIdentifier, $contentType) {
                $language = $this->language($locale);
                $this->validateText($slug, $title, $parentId === null);
                if ($parentId !== null && Page::query()->lockForUpdate()->find($parentId) === null) {
                    throw new PageOperationException("Parent page [$parentId] is not available.");
                }
                $this->ensureSlugIsAvailable($language->id, $slug);
                $type = $this->contentType($contentType);
                $template = $this->template($templateIdentifier);
                $page = Page::query()->create([
                    'parent_id' => $parentId,
                    'uses_explicit_template' => $templateIdentifier !== null,
                    'explicit_template_id' => $templateIdentifier === null ? null : $template->id,
                    'presentation_key' => 'public.page.standard',
                    'content_type' => $type->identifier(),
                    'is_published' => false,
                ]);
                foreach (Language::query()->orderBy('id')->get() as $installed) {
                    PageTranslation::query()->create([
                        'page_id' => $page->id,
                        'language_id' => $installed->id,
                        'title' => $installed->id === $language->id ? $title : null,
                        'slug' => $installed->id === $language->id ? $slug : null,
                        'is_published' => false,
                    ]);
                }
                $translation = PageTranslation::query()->where('page_id', $page->id)->where('language_id', $language->id)->first();
                $this->audit->record('page.create', 'page', $page->id, null, $this->pageState($page, $translation));

                return $translation;
            }, attempts: 5);
        } catch (QueryException $exception) {
            $this->throwExpectedConstraintError($exception);
        }
    }

    public function addLanguageStubs(Language $language): void
    {
        $existing = PageTranslation::query()->where('language_id', $language->id)->pluck('page_id');
        foreach (Page::query()->whereNotIn('id', $existing)->orderBy('id')->pluck('id') as $pageId) {
            PageTranslation::query()->create([
                'page_id' => $pageId,
                'language_id' => $language->id,
                'title' => null,
                'slug' => null,
                'is_published' => false,
            ]);
        }
    }

    public function translate(int $pageId, string $locale, string $slug, string $title): PageTranslation
    {
        try {
            return DB::transaction(function () use ($pageId, $locale, $slug, $title) {
                return $this->updateTranslation($pageId, $locale, $slug, $title);
            }, attempts: 5);
        } catch (QueryException $exception) {
            $this->throwExpectedConstraintError($exception);
        }
    }

    public function updateTranslationAndContent(int $pageId, string $locale, string $slug, string $title, ?string $html): PageTranslation
    {
        try {
            return DB::transaction(function () use ($pageId, $locale, $slug, $title, $html) {
                $translation = $this->updateTranslation($pageId, $locale, $slug, $title);
                if ($html === null) {
                    return $translation;
                }
                $type = $this->types->get($translation->page->content_type);
                $key = $type->contentKey();
                if ($key === null) {
                    throw new PageOperationException("Content type [{$type->identifier()}] has no editable content.");
                }
                $this->saveContent($translation, $key, $html);

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
            $page = $translation->page;
            if ($translation->title === null || $translation->slug === null) {
                throw new PageOperationException('The translation is incomplete and cannot be published.');
            }
            $before = $this->pageState($page, $translation);
            if (! $page->is_published) {
                $page->update(['is_published' => true]);
            }
            if (! $translation->is_published) {
                $translation->update(['is_published' => true]);
            }
            $after = $this->pageState($page->fresh(), $translation->fresh());
            if ($before !== $after) {
                $this->audit->record('page.publish', 'page_translation', $translation->id, $before, $after);
            }
        }, attempts: 5);
    }

    public function unpublish(int $pageId, string $locale): void
    {
        DB::transaction(function () use ($pageId, $locale) {
            $translation = $this->translation($pageId, $locale);
            $language = $this->language($locale);
            $this->guardHomeUnpublish($pageId, $language->id);
            $before = $this->pageState($translation->page, $translation);
            if ($translation->is_published) {
                $translation->update(['is_published' => false]);
                $this->audit->record('page.unpublish', 'page_translation', $translation->id, $before, $this->pageState($translation->page->fresh(), $translation->fresh()));
            }
        }, attempts: 5);
    }

    public function move(int $pageId, ?int $parentId): void
    {
        DB::transaction(function () use ($pageId, $parentId) {
            $page = Page::query()->lockForUpdate()->find($pageId);
            if ($page === null) {
                throw new PageOperationException("Page [$pageId] is not available.");
            }
            if ($parentId === $page->id || ($parentId !== null && $this->isDescendantOf($parentId, $pageId))) {
                throw new PageOperationException('A page cannot be moved below itself or its descendants.');
            }
            if ($parentId !== null && Page::query()->lockForUpdate()->find($parentId) === null) {
                throw new PageOperationException("Parent page [$parentId] is not available.");
            }
            $this->assertMoveKeepsPublishedAccessible($page, $parentId);
            $before = $page->parent_id;
            if ($page->parent_id !== $parentId) {
                $page->update(['parent_id' => $parentId]);
                $this->audit->record('page.move', 'page', $page->id, ['parent_id' => $before], ['parent_id' => $parentId]);
            }
        }, attempts: 5);
    }

    public function delete(int $pageId): void
    {
        DB::transaction(function () use ($pageId) {
            $page = Page::query()->lockForUpdate()->find($pageId);
            if ($page === null) {
                throw new PageOperationException("Page [$pageId] is not available.");
            }
            if (Page::query()->where('parent_id', $pageId)->lockForUpdate()->exists()) {
                throw new PageOperationException('A page with descendants cannot be removed.');
            }
            $homes = $this->homes->homePageIds();
            if (in_array($pageId, $homes, true)) {
                throw new PageOperationException('A home page cannot be removed.');
            }
            $references = $this->menuReferences->referencesForPage($pageId);
            if ($references !== []) {
                throw new PageOperationException('The page is referenced by menu item ['.reset($references).'].');
            }
            $before = $this->pageIdentityState($page);
            foreach (PageTranslation::query()->where('page_id', $pageId)->pluck('id') as $translationId) {
                PageTranslationContent::query()->where('page_translation_id', $translationId)->delete();
            }
            PageTranslation::query()->where('page_id', $pageId)->delete();
            $page->delete();
            $this->audit->record('page.delete', 'page', $pageId, $before, null);
        }, attempts: 5);
    }

    public function setContent(int $pageId, string $locale, string $key, string $html): PageTranslationContent
    {
        return DB::transaction(function () use ($pageId, $locale, $key, $html) {
            $translation = $this->translation($pageId, $locale);

            return $this->saveContent($translation, $key, $html);
        }, attempts: 5);
    }

    public function changeTemplate(int $pageId, ?string $templateIdentifier): void
    {
        DB::transaction(function () use ($pageId, $templateIdentifier) {
            $page = Page::query()->lockForUpdate()->find($pageId);
            if ($page === null) {
                throw new PageOperationException("Page [$pageId] is not available.");
            }
            $template = $this->template($templateIdentifier);
            $before = $this->pageIdentityState($page);
            $page->update([
                'uses_explicit_template' => $templateIdentifier !== null,
                'explicit_template_id' => $templateIdentifier === null ? null : $template->id,
            ]);
            $after = $this->pageIdentityState($page->fresh());
            if ($before !== $after) {
                $this->audit->record('page.template.change', 'page', $page->id, $before, $after);
            }
        }, attempts: 5);
    }

    public function content(int $pageId, string $locale, string $key): string
    {
        $this->validateContentKey($key);
        $value = $this->rawContent($pageId, $locale, $key);
        if ($value !== null) {
            return $value;
        }
        if (config('core.ui_catalog_fallback_mode') === 'base') {
            $default = LanguageSetting::query()->with('frontendDefaultLanguage')->findOrFail(1)->frontendDefaultLanguage;
            if ($default->locale !== $locale) {
                $fallback = $this->rawContent($pageId, $default->locale, $key);
                if ($fallback !== null) {
                    return $fallback;
                }
            }
        }

        return $key;
    }

    /** @return array{translation: PageTranslation, template: CmsTemplate, presentation: PresentationResolution} */
    public function home(string $locale): array
    {
        $language = Language::query()->where('locale', $locale)->where('is_active', true)->first();
        if ($language === null) {
            throw new PageOperationException('The requested language is unavailable.');
        }
        $homes = $this->homes->homePageIds();
        $pageId = $homes[$language->id] ?? null;
        $translation = $pageId === null ? null : PageTranslation::query()->with('page')->where('page_id', $pageId)->where('language_id', $language->id)->first();
        if ($translation === null || ! $this->isPublic($translation, $language->id)) {
            throw new PageOperationException('No public home page is available.');
        }
        $template = $this->templateFor($translation->page);
        $presentation = $this->presentations->resolve(CmsPresentation::PublicPageStandard, $template);
        $this->templateUi->validateStandard($presentation->effectiveTemplate, $presentation->presentationTemplate);

        return ['translation' => $translation, 'template' => $presentation->effectiveTemplate, 'presentation' => $presentation];
    }

    /** @return array{translation: PageTranslation, template: CmsTemplate, presentation: PresentationResolution} */
    public function resolve(string $locale, array $slugs): array
    {
        if ($slugs === []) {
            return $this->home($locale);
        }

        $language = Language::query()->where('locale', $locale)->where('is_active', true)->first();
        $translation = $language === null ? null : PageTranslation::query()->with('page')
            ->where('language_id', $language->id)->where('slug', end($slugs))->first();
        if ($translation === null || ! $this->matchesHierarchy($translation, $language->id, $slugs) || ! $this->isPublic($translation, $language->id)) {
            throw new PageOperationException('The requested page is unavailable.');
        }
        $template = $this->templateFor($translation->page);
        $presentation = $this->presentations->resolve(CmsPresentation::PublicPageStandard, $template);
        $this->templateUi->validateStandard($presentation->effectiveTemplate, $presentation->presentationTemplate);

        return ['translation' => $translation, 'template' => $presentation->effectiveTemplate, 'presentation' => $presentation];
    }

    private function contentType(?string $identifier): ContentType
    {
        $type = $this->types->get($identifier ?? 'rich-text');
        if ($type->isSystem()) {
            throw new PageOperationException("Content type [{$type->identifier()}] cannot be used to create editorial pages.");
        }

        return $type;
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

    private function updateTranslation(int $pageId, string $locale, string $slug, string $title): PageTranslation
    {
        $language = $this->language($locale);
        $page = Page::query()->lockForUpdate()->find($pageId);
        if ($page === null) {
            throw new PageOperationException("Page [$pageId] is not available.");
        }
        $this->validateText($slug, $title, $page->parent_id === null);
        $translation = PageTranslation::query()->where('page_id', $page->id)->where('language_id', $language->id)->lockForUpdate()->first();
        if ($translation === null) {
            throw new PageOperationException("Page [$pageId] has no translation for [$locale].");
        }
        $this->ensureSlugIsAvailable($language->id, $slug, $translation->id);
        $before = $this->pageState($page, $translation);
        $translation->update(['title' => $title, 'slug' => $slug]);
        $this->audit->record('page.translate', 'page_translation', $translation->id, $before, $this->pageState($page, $translation->fresh()));

        return $translation;
    }

    private function saveContent(PageTranslation $translation, string $key, string $html): PageTranslationContent
    {
        $this->validateContentKey($key);
        if (strlen($html) > self::MAX_CONTENT_BYTES) {
            throw new PageOperationException('The content exceeds the 4 MiB limit.');
        }
        $html = $this->embedMarkers->normalize($html);
        $this->embedMarkers->parse($html);
        $type = $this->types->get($translation->page->content_type);
        $sanitized = $this->sanitizer->sanitize($type, $html);
        $before = $this->contentState($translation, $key);
        $content = PageTranslationContent::query()->updateOrCreate(
            ['page_translation_id' => $translation->id, 'key' => $key],
            ['content' => $sanitized],
        );
        $this->audit->record('page.content.save', 'page_translation_content', $content->id, $before, $this->contentState($translation, $key));

        return $content;
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
        $presentation = $this->presentations->resolve(CmsPresentation::PublicPageStandard, $template);
        $this->templateUi->validateStandard($presentation->effectiveTemplate, $presentation->presentationTemplate);

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

    private function guardHomeUnpublish(int $pageId, int $languageId): void
    {
        $homes = $this->homes->homePageIds();
        $homePageId = $homes[$languageId] ?? null;
        if ($homePageId === null) {
            return;
        }
        if ($homePageId === $pageId || $this->isAncestorOf($pageId, $homePageId)) {
            throw new PageOperationException('The home page cannot be unpublished without a replacement.');
        }
    }

    private function isAncestorOf(int $ancestorId, int $pageId): bool
    {
        $current = Page::query()->find($pageId);
        while ($current !== null) {
            if ($current->parent_id === $ancestorId) {
                return true;
            }
            $current = $current->parent_id === null ? null : Page::query()->find($current->parent_id);
        }

        return false;
    }

    private function isDescendantOf(int $candidateId, int $pageId): bool
    {
        return $this->isAncestorOf($pageId, $candidateId);
    }

    private function assertMoveKeepsPublishedAccessible(Page $page, ?int $newParentId): void
    {
        $languageIds = PageTranslation::query()->where('page_id', $page->id)->where('is_published', true)->pluck('language_id')->unique();
        foreach ($this->descendantIds($page->id) as $descendantId) {
            $languageIds = $languageIds->merge(PageTranslation::query()->where('page_id', $descendantId)->where('is_published', true)->pluck('language_id'))->unique();
        }
        foreach ($languageIds as $languageId) {
            if (! $this->ancestorsPublic($newParentId, $languageId)) {
                throw new PageOperationException('The move would leave a published translation inaccessible.');
            }
        }
    }

    private function ancestorsPublic(?int $pageId, int $languageId): bool
    {
        $current = $pageId;
        while ($current !== null) {
            $page = Page::query()->find($current);
            if ($page === null || ! $page->is_published || ! PageTranslation::query()->where('page_id', $page->id)->where('language_id', $languageId)->where('is_published', true)->exists()) {
                return false;
            }
            $current = $page->parent_id;
        }

        return true;
    }

    /** @return array<int, int> */
    private function descendantIds(int $pageId): array
    {
        $ids = [];
        $frontier = [$pageId];
        while ($frontier !== []) {
            $children = Page::query()->whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = array_merge($ids, $children);
            $frontier = $children;
        }

        return $ids;
    }

    private function ensureSlugIsAvailable(int $languageId, string $slug, ?int $exceptTranslationId = null): void
    {
        $query = PageTranslation::query()->where('language_id', $languageId)->where('slug', $slug);
        if ($exceptTranslationId !== null) {
            $query->where('id', '!=', $exceptTranslationId);
        }
        if ($query->lockForUpdate()->exists()) {
            throw new PageOperationException("Slug [$slug] is already in use for this language.");
        }
    }

    private function matchesHierarchy(PageTranslation $translation, int $languageId, array $slugs): bool
    {
        $actual = [];
        $page = $translation->page;
        while ($page !== null) {
            $segment = PageTranslation::query()->where('page_id', $page->id)->where('language_id', $languageId)->value('slug');
            if (! is_string($segment)) {
                return false;
            }
            array_unshift($actual, $segment);
            $page = $page->parent;
        }

        return $actual === $slugs;
    }

    private function throwExpectedConstraintError(QueryException $exception): never
    {
        if (str_starts_with((string) $exception->getCode(), '23')) {
            throw new PageOperationException('The page data conflicts with an existing Page or translation.', previous: $exception);
        }

        throw $exception;
    }

    private function validateText(string $slug, string $title, bool $isRoot = false): void
    {
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) || strlen($slug) > 100
            || ($isRoot && preg_match('/^[a-z]{2}(?:-[a-z]{2})?$/D', $slug))
            || $title === '' || mb_strlen($title, 'UTF-8') > 255 || preg_match('/[\x00-\x1F\x7F]/', $title)) {
            throw new PageOperationException('The page title or slug is invalid.');
        }
    }

    private function validateContentKey(string $key): void
    {
        if (! preg_match('/^[a-z][a-z0-9]*(?:\.[a-z][a-z0-9]*)*$/D', $key) || strlen($key) > 255) {
            throw new PageOperationException('The content key is invalid.');
        }
    }

    private function rawContent(int $pageId, string $locale, string $key): ?string
    {
        $language = Language::query()->where('locale', $locale)->first();
        if ($language === null) {
            return null;
        }
        $translation = PageTranslation::query()->where('page_id', $pageId)->where('language_id', $language->id)->first();
        if ($translation === null) {
            return null;
        }
        $content = PageTranslationContent::query()->where('page_translation_id', $translation->id)->where('key', $key)->value('content');
        if (! is_string($content) || $content === '') {
            return null;
        }

        return $content;
    }

    /** @return array<string, int|string|bool|null> */
    private function pageState(Page $page, PageTranslation $translation): array
    {
        return [
            'page_id' => $page->id,
            'parent_id' => $page->parent_id,
            'uses_explicit_template' => $page->uses_explicit_template,
            'explicit_template_id' => $page->explicit_template_id,
            'presentation_key' => $page->presentation_key,
            'content_type' => $page->content_type,
            'page_is_published' => $page->is_published,
            'translation_id' => $translation->id,
            'language_id' => $translation->language_id,
            'slug' => $translation->slug,
            'title' => $translation->title,
            'is_published' => $translation->is_published,
        ];
    }

    /** @return array<string, int|string|bool|null> */
    private function pageIdentityState(Page $page): array
    {
        return [
            'page_id' => $page->id,
            'parent_id' => $page->parent_id,
            'uses_explicit_template' => $page->uses_explicit_template,
            'explicit_template_id' => $page->explicit_template_id,
            'presentation_key' => $page->presentation_key,
            'content_type' => $page->content_type,
            'page_is_published' => $page->is_published,
        ];
    }

    /** @return array<string, int|string> */
    private function contentState(PageTranslation $translation, string $key): array
    {
        return [
            'page_translation_id' => $translation->id,
            'key' => $key,
        ];
    }
}
