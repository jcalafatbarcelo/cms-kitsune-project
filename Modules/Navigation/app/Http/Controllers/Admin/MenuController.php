<?php

namespace Modules\Navigation\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminPresentation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
            'items' => MenuItem::query()->orderBy('menu_id')->orderBy('language_id')->orderBy('position')->get(),
            'homes' => MenuItemHome::query()->get()->keyBy('language_id'),
            'languages' => Language::query()->orderBy('id')->get(),
            'pages' => Page::query()->orderBy('id')->get(),
        ]);
    }

    public function storeMenu(Request $request): RedirectResponse
    {
        $identifier = (string) $request->input('identifier');

        try {
            $this->menus->create($identifier);
        } catch (NavigationOperationException $exception) {
            return back()->withErrors(['operation' => $exception->getMessage()]);
        }

        return redirect()->route('admin.menus.index');
    }

    public function storeItem(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'menu' => ['required', 'string'],
            'locale' => ['required', 'string'],
            'page_id' => ['required', 'integer'],
            'label' => ['required', 'string'],
            'parent_id' => ['nullable', 'integer'],
            'position' => ['nullable', 'integer'],
        ]);
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
            return back()->withErrors(['operation' => $exception->getMessage()]);
        }

        return redirect()->route('admin.menus.index');
    }

    public function updateItem(int $itemId, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'page_id' => ['required', 'integer'],
            'label' => ['required', 'string'],
        ]);

        try {
            $this->menus->updateItem($itemId, (int) $data['page_id'], $data['label']);
        } catch (NavigationOperationException $exception) {
            return back()->withErrors(['operation' => $exception->getMessage()]);
        }

        return redirect()->route('admin.menus.index');
    }

    public function moveItem(int $itemId, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'position' => ['required', 'integer'],
            'parent_id' => ['nullable', 'integer'],
        ]);
        $parentId = $data['parent_id'] ?? null;

        try {
            $this->menus->moveItem($itemId, (int) $data['position'], $parentId === null ? null : (int) $parentId);
        } catch (NavigationOperationException $exception) {
            return back()->withErrors(['operation' => $exception->getMessage()]);
        }

        return redirect()->route('admin.menus.index');
    }

    public function removeItem(int $itemId): RedirectResponse
    {
        try {
            $this->menus->removeItem($itemId);
        } catch (NavigationOperationException $exception) {
            return back()->withErrors(['operation' => $exception->getMessage()]);
        }

        return redirect()->route('admin.menus.index');
    }

    public function markHome(int $itemId): RedirectResponse
    {
        try {
            $this->menus->markHome($itemId);
        } catch (NavigationOperationException $exception) {
            return back()->withErrors(['operation' => $exception->getMessage()]);
        }

        return redirect()->route('admin.menus.index');
    }

    public function unmarkHome(Request $request): RedirectResponse
    {
        if (! $request->boolean('confirm')) {
            return back()->withErrors(['confirm' => 'The home removal requires confirmation.']);
        }

        $locale = (string) $request->input('locale');

        try {
            $this->menus->unmarkHome($locale);
        } catch (NavigationOperationException $exception) {
            return back()->withErrors(['operation' => $exception->getMessage()]);
        }

        return redirect()->route('admin.menus.index');
    }
}
