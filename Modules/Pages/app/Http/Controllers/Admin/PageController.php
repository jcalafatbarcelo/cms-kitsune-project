<?php

namespace Modules\Pages\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminPresentation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Core\Language\Models\Language;
use Modules\Core\Template\Enums\CmsPresentation;
use Modules\Core\Template\Models\CmsTemplate;
use Modules\Pages\Contracts\HomePageLocator;
use Modules\Pages\Exceptions\PageOperationException;
use Modules\Pages\Models\Page;
use Modules\Pages\Services\ContentTypeRegistry;
use Modules\Pages\Services\PageManager;

class PageController extends Controller
{
    public function __construct(
        private readonly PageManager $pages,
        private readonly ContentTypeRegistry $types,
        private readonly HomePageLocator $homes,
    ) {}

    public function index(AdminPresentation $presentation): Response
    {
        return $presentation->render(CmsPresentation::SystemAdminPages, data: $this->listData());
    }

    public function store(Request $request, AdminPresentation $presentation): Response|RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', 'string'],
            'slug' => ['required', 'string'],
            'title' => ['required', 'string'],
            'parent_id' => ['nullable', 'integer'],
            'template' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
        ]);

        try {
            $this->pages->create(
                $data['locale'],
                $data['slug'],
                $data['title'],
                ($data['parent_id'] ?? null) === null ? null : (int) $data['parent_id'],
                ($data['template'] ?? null) === null ? null : (string) $data['template'],
                ($data['type'] ?? null) === null ? null : (string) $data['type'],
            );
        } catch (PageOperationException $exception) {
            return $this->backWithError($presentation, $exception, $data);
        }

        return redirect()->route('admin.pages.index');
    }

    public function edit(int $pageId, AdminPresentation $presentation): Response
    {
        $page = Page::query()->with(['translations' => fn ($query) => $query->with('contents')->orderBy('language_id')])->findOrFail($pageId);

        return $presentation->render(CmsPresentation::SystemAdminPagesEdit, data: array_merge($this->listData(), [
            'page' => $page,
            'contentKey' => $this->types->get($page->content_type)->contentKey(),
            'editor' => $this->types->get($page->content_type)->editor(),
        ]));
    }

    public function update(int $pageId, Request $request, AdminPresentation $presentation): Response|RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', 'string'],
            'slug' => ['required', 'string'],
            'title' => ['required', 'string'],
            'content' => ['nullable', 'string'],
        ]);

        try {
            $this->pages->updateTranslationAndContent($pageId, $data['locale'], $data['slug'], $data['title'], $data['content'] ?? null);
        } catch (PageOperationException $exception) {
            return $this->backToEditWithError($pageId, $presentation, $exception, $data);
        }

        return redirect()->route('admin.pages.edit', $pageId);
    }

    public function publish(int $pageId, Request $request): RedirectResponse
    {
        $locale = (string) $request->input('locale');

        try {
            $this->pages->publish($pageId, $locale);
        } catch (PageOperationException $exception) {
            return back()->withErrors(['operation' => $exception->getMessage()]);
        }

        return back();
    }

    public function unpublish(int $pageId, Request $request): RedirectResponse
    {
        $locale = (string) $request->input('locale');

        try {
            $this->pages->unpublish($pageId, $locale);
        } catch (PageOperationException $exception) {
            return back()->withErrors(['operation' => $exception->getMessage()]);
        }

        return back();
    }

    public function move(int $pageId, Request $request): RedirectResponse
    {
        $parentId = $request->input('parent_id');

        try {
            $this->pages->move($pageId, $parentId === null ? null : (int) $parentId);
        } catch (PageOperationException $exception) {
            return back()->withErrors(['operation' => $exception->getMessage()]);
        }

        return back();
    }

    public function changeTemplate(int $pageId, Request $request): RedirectResponse
    {
        $data = $request->validate(['template' => ['nullable', 'string']]);

        try {
            $this->pages->changeTemplate($pageId, ($data['template'] ?? null) === null || $data['template'] === '' ? null : $data['template']);
        } catch (PageOperationException $exception) {
            return back()->withErrors(['operation' => $exception->getMessage()]);
        }

        return redirect()->route('admin.pages.edit', $pageId);
    }

    public function delete(int $pageId, Request $request): RedirectResponse
    {
        try {
            $this->pages->delete($pageId);
        } catch (PageOperationException $exception) {
            return back()->withErrors(['operation' => $exception->getMessage()]);
        }

        return redirect()->route('admin.pages.index');
    }

    /** @return array<string, mixed> */
    private function listData(): array
    {
        return [
            'pages' => Page::query()->with(['translations' => fn ($query) => $query->orderBy('language_id'), 'children'])->orderBy('id')->get(),
            'languages' => Language::query()->orderBy('id')->get(),
            'contentTypes' => $this->types->selectable(),
            'templates' => CmsTemplate::query()->where('is_active', true)->get(),
            'homePageIds' => $this->homes->homePageIds(),
        ];
    }

    /** @param array<string, mixed> $data */
    private function backWithError(AdminPresentation $presentation, PageOperationException $exception, array $data): Response
    {
        return $presentation->render(CmsPresentation::SystemAdminPages, data: array_merge($this->listData(), [
            'error' => $exception->getMessage(),
            'input' => $data,
        ]));
    }

    /** @param array<string, mixed> $data */
    private function backToEditWithError(int $pageId, AdminPresentation $presentation, PageOperationException $exception, array $data): Response
    {
        $page = Page::query()->with(['translations' => fn ($query) => $query->with('contents')->orderBy('language_id')])->findOrFail($pageId);

        return $presentation->render(CmsPresentation::SystemAdminPagesEdit, data: array_merge($this->listData(), [
            'page' => $page,
            'contentKey' => $this->types->get($page->content_type)->contentKey(),
            'editor' => $this->types->get($page->content_type)->editor(),
            'error' => $exception->getMessage(),
            'input' => $data,
        ]));
    }
}
