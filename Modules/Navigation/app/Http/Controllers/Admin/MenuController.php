<?php

namespace Modules\Navigation\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminPresentation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Modules\Core\Language\Models\Language;
use Modules\Core\Template\Enums\CmsPresentation;
use Modules\Navigation\Exceptions\NavigationOperationException;
use Modules\Navigation\Models\Menu;
use Modules\Navigation\Models\MenuItem;
use Modules\Navigation\Models\MenuItemHome;
use Modules\Navigation\Services\MenuManager;
use Modules\Pages\Models\Page;

class MenuController extends Controller
{
    public function __construct(private readonly MenuManager $menus) {}

    public function index(AdminPresentation $presentation): Response
    {
        return $presentation->render(CmsPresentation::SystemAdminMenus, data: [
            'menus' => Menu::query()->orderBy('id')->get(),
        ]);
    }

    public function create(AdminPresentation $presentation): Response
    {
        return $presentation->render(CmsPresentation::SystemAdminMenusNew);
    }

    public function show(int $menuId, AdminPresentation $presentation, Request $request): Response
    {
        $menu = Menu::query()->findOrFail($menuId);
        $languages = Language::query()->orderBy('id')->get();
        $allItems = MenuItem::query()->where('menu_id', $menu->id)
            ->orderBy('language_id')->orderBy('position')->orderBy('id')->get();
        $pages = Page::query()->with('translations')->orderBy('name')->get();
        $tree = $this->menuTreePresentation($allItems, $languages);
        $pagesById = $pages->keyBy('id');
        $translations = $pages->flatMap(fn (Page $page) => $page->translations->mapWithKeys(
            fn ($translation): array => [$page->id.':'.$translation->language_id => $translation],
        ));
        $itemRows = $tree['rows']->map(function (array $row, int $itemId) use ($pagesById, $translations): array {
            $item = $row['item'];
            $page = $pagesById->get($item->page_id);
            $translation = $translations->get($item->page_id.':'.$item->language_id);

            return array_merge($row, [
                'page' => $page,
                'pageIsPublic' => $page !== null && $page->is_published && $translation?->is_published,
            ]);
        });
        $homes = MenuItemHome::query()->get();

        return $presentation->render(CmsPresentation::SystemAdminMenusShow, data: [
            'menu' => $menu,
            'items' => $tree['items'],
            'itemRows' => $itemRows,
            'menuItemsData' => $tree['data'],
            'addItem' => $this->addItemPresentation($request, $languages, $pages, $tree),
            'activeTab' => $request->old('form_context') === 'add-item' ? 'add-item-tab' : null,
            'homeItemIds' => $homes->pluck('menu_item_id')->flip(),
            'languages' => $languages,
            'pages' => $pages,
        ]);
    }

    public function storeMenu(Request $request): RedirectResponse
    {
        $identifier = (string) $request->input('identifier');

        try {
            $this->menus->create($identifier);
        } catch (NavigationOperationException $exception) {
            return redirect()->route('admin.menus.create')->withInput()->withErrors(['operation' => $exception->getMessage()]);
        }

        return $this->flash('success', 'admin.menus.created');
    }

    public function storeItem(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'menu' => ['required', 'string'],
            'locale' => ['required', 'string'],
            'page_id' => ['required', 'integer'],
            'label' => ['required', 'string'],
            'parent_id' => ['nullable', 'integer'],
            'position' => ['nullable', 'integer'],
        ]);
        if ($validator->fails()) {
            if ($validator->errors()->has('menu')) {
                return redirect()->route('admin.menus.index')->withInput()->withErrors($validator);
            }

            $menu = Menu::query()->where('identifier', $request->input('menu'))->first();
            if ($menu === null) {
                return redirect()->route('admin.menus.index')->withInput()->withErrors(['menu' => 'The selected menu is unavailable.']);
            }

            return redirect()->route('admin.menus.show', $menu)->withInput()->withErrors($validator);
        }

        $data = $validator->validated();
        $menu = Menu::query()->where('identifier', $data['menu'])->first();
        if ($menu === null) {
            return redirect()->route('admin.menus.index')->withInput()->withErrors(['menu' => 'The selected menu is unavailable.']);
        }

        $parentId = $data['parent_id'] ?? null;
        $position = $data['position'] ?? null;

        try {
            $this->menus->createItem(
                $data['menu'],
                $data['locale'],
                (int) $data['page_id'],
                $data['label'],
                $parentId === null ? null : (int) $parentId,
                $position === null ? null : (int) $position,
            );
        } catch (NavigationOperationException $exception) {
            return $this->errorMenu($menu, 'operation', $exception->getMessage());
        }

        return $this->flashMenu($menu, 'success', 'admin.menus.item-created');
    }

    public function updateItem(int $itemId, Request $request): RedirectResponse
    {
        $menu = MenuItem::query()->findOrFail($itemId)->menu;
        $validator = Validator::make($request->all(), [
            'page_id' => ['required', 'integer'],
            'label' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return redirect()->route('admin.menus.show', $menu)->withInput()->withErrors($validator);
        }

        $data = $validator->validated();

        try {
            $this->menus->updateItem($itemId, (int) $data['page_id'], $data['label']);
        } catch (NavigationOperationException $exception) {
            return $this->errorMenu($menu, 'operation', $exception->getMessage());
        }

        return $this->flashItem($itemId, 'success', 'admin.menus.item-updated');
    }

    public function moveItem(int $itemId, Request $request): RedirectResponse
    {
        $menu = MenuItem::query()->findOrFail($itemId)->menu;
        $validator = Validator::make($request->all(), [
            'position' => ['required', 'integer'],
            'parent_id' => ['nullable', 'integer'],
        ]);
        if ($validator->fails()) {
            return redirect()->route('admin.menus.show', $menu)->withInput()->withErrors($validator);
        }

        $data = $validator->validated();
        $parentId = $data['parent_id'] ?? null;

        try {
            $this->menus->moveItem($itemId, (int) $data['position'], $parentId === null ? null : (int) $parentId);
        } catch (NavigationOperationException $exception) {
            return $this->errorMenu($menu, 'operation', $exception->getMessage());
        }

        return $this->flashItem($itemId, 'success', 'admin.menus.item-moved');
    }

    public function removeItem(int $itemId): RedirectResponse
    {
        $menu = MenuItem::query()->findOrFail($itemId)->menu;

        try {
            $this->menus->removeItem($itemId);
        } catch (NavigationOperationException $exception) {
            return $this->errorMenu($menu, 'operation', $exception->getMessage());
        }

        return $this->flashMenu($menu, 'success', 'admin.menus.item-removed');
    }

    public function markHome(int $itemId): RedirectResponse
    {
        $menu = MenuItem::query()->findOrFail($itemId)->menu;

        try {
            $this->menus->markHome($itemId);
        } catch (NavigationOperationException $exception) {
            return $this->errorMenu($menu, 'operation', $exception->getMessage());
        }

        return $this->flashItem($itemId, 'success', 'admin.menus.home-assigned');
    }

    public function unmarkHome(Request $request): RedirectResponse
    {
        $selectedMenu = Menu::query()->where('identifier', (string) $request->input('menu'))->first();
        if (! $request->boolean('confirm')) {
            return $selectedMenu === null
                ? redirect()->route('admin.menus.index')->withInput()->withErrors(['confirm' => 'The home removal requires confirmation.'])
                : $this->errorMenu($selectedMenu, 'confirm', 'The home removal requires confirmation.');
        }

        $locale = (string) $request->input('locale');
        $menu = MenuItemHome::query()->with('menuItem')->whereKey(Language::query()->where('locale', $locale)->value('id'))->first()?->menuItem?->menu;

        try {
            $this->menus->unmarkHome($locale);
        } catch (NavigationOperationException $exception) {
            $target = $menu ?? $selectedMenu;

            return $target === null
                ? redirect()->route('admin.menus.index')->withInput()->withErrors(['operation' => $exception->getMessage()])
                : $this->errorMenu($target, 'operation', $exception->getMessage());
        }

        return $menu === null
            ? $this->flash('warning', 'admin.menus.home-removed')
            : $this->flashMenu($menu, 'warning', 'admin.menus.home-removed');
    }

    private function flash(string $type, string $key): RedirectResponse
    {
        return redirect()->route('admin.menus.index')->with('admin_flash', ['type' => $type, 'key' => $key]);
    }

    private function flashItem(int $itemId, string $type, string $key): RedirectResponse
    {
        return $this->flashMenuForItem($itemId, $type, $key);
    }

    private function flashMenuForItem(int $itemId, string $type, string $key): RedirectResponse
    {
        $menu = MenuItem::query()->findOrFail($itemId)->menu;

        return $this->flashMenu($menu, $type, $key);
    }

    private function flashMenu(Menu $menu, string $type, string $key): RedirectResponse
    {
        return redirect()->route('admin.menus.show', $menu)
            ->with('admin_flash', ['type' => $type, 'key' => $key]);
    }

    private function errorMenu(Menu $menu, string $field, string $message): RedirectResponse
    {
        return redirect()->route('admin.menus.show', $menu)->withInput()->withErrors([$field => $message]);
    }

    /**
     * @param  Collection<int, MenuItem>  $items
     * @param  Collection<int, Language>  $languages
     * @return array{
     *     items: Collection<int, MenuItem>,
     *     rows: Collection<int, array{item: MenuItem, locale: string, excludedIds: array<int, int>, parentCandidates: Collection<int, MenuItem>, destinationSiblings: Collection<int, MenuItem>}>,
     *     data: Collection<int, array{id: int, locale: string, parentId: ?int, position: int, label: string}>,
     *     localizedItems: array<int, array<int, MenuItem>>,
     *     children: array<string, array<int, MenuItem>>
     * }
     */
    private function menuTreePresentation(Collection $items, Collection $languages): array
    {
        $localeByLanguage = $languages->pluck('locale', 'id')->all();
        $localizedItems = [];
        $children = [];

        foreach ($items as $item) {
            $localizedItems[$item->language_id][] = $item;
            $children[$this->parentKey($item->language_id, $item->parent_id)][] = $item;
        }

        $ordered = collect();
        $descendants = [];
        $visit = function (MenuItem $item, int $depth) use (&$visit, &$descendants, $children, $ordered): array {
            $item->setAttribute('presentation_depth', $depth);
            $ordered->push($item);
            $itemDescendants = [];

            foreach ($children[$this->parentKey($item->language_id, $item->id)] ?? [] as $child) {
                $itemDescendants[] = $child->id;
                $itemDescendants = array_merge($itemDescendants, $visit($child, $depth + 1));
            }

            $descendants[$item->id] = $itemDescendants;

            return $itemDescendants;
        };

        foreach ($languages as $language) {
            foreach ($children[$this->parentKey($language->id, null)] ?? [] as $root) {
                $visit($root, 0);
            }
        }

        $rows = $ordered->mapWithKeys(function (MenuItem $item) use ($children, $descendants, $localizedItems, $localeByLanguage): array {
            $excludedIds = array_merge([$item->id], $descendants[$item->id]);
            $excluded = array_fill_keys($excludedIds, true);
            $parentCandidates = collect(array_values(array_filter(
                $localizedItems[$item->language_id] ?? [],
                fn (MenuItem $candidate): bool => ! isset($excluded[$candidate->id]),
            )));
            $destinationSiblings = collect(array_values(array_filter(
                $children[$this->parentKey($item->language_id, $item->parent_id)] ?? [],
                fn (MenuItem $candidate): bool => $candidate->id !== $item->id,
            )));

            return [$item->id => [
                'item' => $item,
                'locale' => $localeByLanguage[$item->language_id],
                'excludedIds' => $excludedIds,
                'parentCandidates' => $parentCandidates,
                'destinationSiblings' => $destinationSiblings,
            ]];
        });

        return [
            'items' => $ordered,
            'rows' => $rows,
            'data' => $items->map(fn (MenuItem $item): array => [
                'id' => $item->id,
                'locale' => $localeByLanguage[$item->language_id],
                'parentId' => $item->parent_id,
                'position' => $item->position,
                'label' => $item->label,
            ])->values(),
            'localizedItems' => $localizedItems,
            'children' => $children,
        ];
    }

    /**
     * @param  Collection<int, Language>  $languages
     * @param  Collection<int, Page>  $pages
     * @param  array{localizedItems: array<int, array<int, MenuItem>>, children: array<string, array<int, MenuItem>>}  $tree
     * @return array{locale: string, pageId: ?int, label: string, parentId: ?int, position: int, parentCandidates: Collection<int, MenuItem>, siblings: Collection<int, MenuItem>}
     */
    private function addItemPresentation(Request $request, Collection $languages, Collection $pages, array $tree): array
    {
        $recover = $request->old('form_context') === 'add-item';
        $oldLocale = $recover ? $request->old('locale') : null;
        $language = is_string($oldLocale) ? $languages->firstWhere('locale', $oldLocale) : null;
        $language ??= $languages->first();
        $parentCandidates = collect($tree['localizedItems'][$language->id] ?? []);
        $oldParentId = $recover ? $this->positiveInteger($request->old('parent_id')) : null;
        $parentId = $oldParentId !== null && $parentCandidates->contains('id', $oldParentId) ? $oldParentId : null;
        $siblings = collect($tree['children'][$this->parentKey($language->id, $parentId)] ?? []);
        $maximumPosition = $siblings->count() + 1;
        $oldPosition = $recover ? $this->positiveInteger($request->old('position')) : null;
        $position = $oldPosition !== null && $oldPosition <= $maximumPosition ? $oldPosition : $maximumPosition;
        $oldPageId = $recover ? $this->positiveInteger($request->old('page_id')) : null;
        $pageId = $oldPageId !== null && $pages->contains('id', $oldPageId) ? $oldPageId : $pages->first()?->id;
        $oldLabel = $recover ? $request->old('label') : null;
        $label = is_string($oldLabel) && $oldLabel !== '' && mb_strlen($oldLabel, 'UTF-8') <= 255
            && preg_match('/[\x00-\x1F\x7F]/', $oldLabel) !== 1 ? $oldLabel : '';

        return compact('parentCandidates', 'siblings', 'parentId', 'position', 'pageId', 'label') + [
            'locale' => $language->locale,
        ];
    }

    private function parentKey(int $languageId, ?int $parentId): string
    {
        return $languageId.':'.($parentId ?? 'root');
    }

    private function positiveInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (! is_string($value) || preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
            return null;
        }

        return (int) $value;
    }
}
