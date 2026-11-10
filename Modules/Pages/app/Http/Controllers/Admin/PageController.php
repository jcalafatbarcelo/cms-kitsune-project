<?php

namespace Modules\Pages\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminPresentation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
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

    public function create(AdminPresentation $presentation): Response
    {
        return $presentation->render(CmsPresentation::SystemAdminPagesNew, data: $this->listData());
    }

    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string'],
            'locale' => ['required', 'string'],
            'slug' => ['required', 'string'],
            'title' => ['required', 'string'],
            'parent_id' => ['nullable', 'integer'],
            'template' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return redirect()->route('admin.pages.create')->withInput()->withErrors($validator);
        }

        $data = $validator->validated();

        try {
            $this->pages->create(
                $data['locale'],
                $data['slug'],
                $data['title'],
                ($data['parent_id'] ?? null) === null ? null : (int) $data['parent_id'],
                ($data['template'] ?? null) === null ? null : (string) $data['template'],
                ($data['type'] ?? null) === null ? null : (string) $data['type'],
                $data['name'],
            );
        } catch (PageOperationException $exception) {
            return redirect()->route('admin.pages.create')->withInput()->withErrors(['operation' => $exception->getMessage()]);
        }

        return $this->flash('success', 'admin.pages.created');
    }

    public function edit(int $pageId, AdminPresentation $presentation): Response
    {
        $page = Page::query()->with(['translations' => fn ($query) => $query->with('contents')->orderBy('language_id')])->findOrFail($pageId);

        return $presentation->render(CmsPresentation::SystemAdminPagesEdit, data: array_merge($this->listData($pageId), [
            'page' => $page,
            'contentKey' => $this->types->get($page->content_type)->contentKey(),
            'editor' => $this->types->get($page->content_type)->editor(),
        ]));
    }

    public function update(int $pageId, Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'locale' => ['required', 'string'],
            'slug' => ['required', 'string'],
            'title' => ['required', 'string'],
            'content' => ['nullable', 'string'],
        ]);
        if ($validator->fails()) {
            return redirect()->route('admin.pages.edit', $pageId)->withInput()->withErrors($validator);
        }

        $data = $validator->validated();

        try {
            $this->pages->updateTranslationAndContent($pageId, $data['locale'], $data['slug'], $data['title'], $data['content'] ?? null);
        } catch (PageOperationException $exception) {
            return $this->detailError($pageId, 'operation', $exception->getMessage());
        }

        return $this->flash('success', 'admin.pages.updated');
    }

    public function publish(int $pageId, Request $request): RedirectResponse
    {
        $locale = (string) $request->input('locale');

        try {
            $this->pages->publish($pageId, $locale);
        } catch (PageOperationException $exception) {
            return $this->detailError($pageId, 'operation', $exception->getMessage());
        }

        return $this->flash('success', 'admin.pages.published');
    }

    public function unpublish(int $pageId, Request $request): RedirectResponse
    {
        $locale = (string) $request->input('locale');

        try {
            $this->pages->unpublish($pageId, $locale);
        } catch (PageOperationException $exception) {
            return $this->detailError($pageId, 'operation', $exception->getMessage());
        }

        return $this->flash('warning', 'admin.pages.unpublished');
    }

    public function move(int $pageId, Request $request): RedirectResponse
    {
        $parentId = $request->input('parent_id');

        try {
            $this->pages->move($pageId, $parentId === null ? null : (int) $parentId);
        } catch (PageOperationException $exception) {
            return $this->detailError($pageId, 'operation', $exception->getMessage());
        }

        return $this->flash('success', 'admin.pages.moved');
    }

    public function changeTemplate(int $pageId, Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), ['template' => ['nullable', 'string']]);
        if ($validator->fails()) {
            return redirect()->route('admin.pages.edit', $pageId)->withInput()->withErrors($validator);
        }

        $data = $validator->validated();

        try {
            $this->pages->changeTemplate($pageId, ($data['template'] ?? null) === null || $data['template'] === '' ? null : $data['template']);
        } catch (PageOperationException $exception) {
            return $this->detailError($pageId, 'operation', $exception->getMessage());
        }

        return $this->flash('success', 'admin.pages.template-updated');
    }

    public function delete(int $pageId, Request $request): RedirectResponse
    {
        try {
            $this->pages->delete($pageId);
        } catch (PageOperationException $exception) {
            return $this->detailError($pageId, 'operation', $exception->getMessage());
        }

        return $this->flash('success', 'admin.pages.deleted');
    }

    private function flash(string $type, string $key): RedirectResponse
    {
        return redirect()->route('admin.pages.index')->with('admin_flash', ['type' => $type, 'key' => $key]);
    }

    /** @return array<string, mixed> */
    private function listData(?int $excludeDescendantsOf = null): array
    {
        $excluded = $excludeDescendantsOf === null ? [] : array_merge([$excludeDescendantsOf], $this->descendantIds($excludeDescendantsOf));

        return [
            'pages' => Page::query()->with(['translations' => fn ($query) => $query->orderBy('language_id'), 'children'])->orderBy('name')->get(),
            'moveCandidates' => Page::query()->whereNotIn('id', $excluded)->orderBy('name')->get(),
            'languages' => Language::query()->orderBy('id')->get(),
            'contentTypes' => $this->types->selectable(),
            'templates' => CmsTemplate::query()->where('is_active', true)->get(),
            'homePageIds' => $this->homes->homePageIds(),
        ];
    }

    private function detailError(int $pageId, string $field, string $message): RedirectResponse
    {
        return redirect()->route('admin.pages.edit', $pageId)->withInput()->withErrors([$field => $message]);
    }

    /** @return array<int, int> */
    private function descendantIds(int $pageId): array
    {
        $ids = [];
        $frontier = [$pageId];
        while ($frontier !== []) {
            $frontier = Page::query()->whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }
}
