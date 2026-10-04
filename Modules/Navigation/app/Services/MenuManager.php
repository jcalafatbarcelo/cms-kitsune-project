<?php

namespace Modules\Navigation\Services;

use App\Services\Admin\AdminAuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Core\Language\Models\Language;
use Modules\Navigation\Exceptions\NavigationOperationException;
use Modules\Navigation\Models\Menu;
use Modules\Navigation\Models\MenuItem;

class MenuManager
{
    public function __construct(private readonly AdminAuditLogger $audit) {}

    public function create(string $identifier): Menu
    {
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $identifier) !== 1 || strlen($identifier) > 100) {
            throw new NavigationOperationException('The menu identifier is invalid.');
        }

        try {
            return DB::transaction(function () use ($identifier) {
                $menu = Menu::query()->create(['identifier' => $identifier]);
                $this->audit->record('menu.create', 'menu', $menu->id, null, $this->menuState($menu));

                return $menu;
            }, attempts: 5);
        } catch (QueryException $exception) {
            if (str_starts_with((string) $exception->getCode(), '23')) {
                throw new NavigationOperationException("Menu [$identifier] already exists.", previous: $exception);
            }

            throw $exception;
        }
    }

    public function createItem(string $menuIdentifier, string $locale, int $pageId, string $label, ?int $parentId = null, ?int $position = null): MenuItem
    {
        return $this->transaction(function () use ($menuIdentifier, $locale, $pageId, $label, $parentId, $position) {
            $menu = $this->menu($menuIdentifier);
            $language = $this->language($locale);
            $parent = $this->parent($parentId, $menu, $language);
            $siblings = $this->siblings($menu->id, $language->id, $parent?->id);
            $position ??= $siblings->count() + 1;
            $this->validatePosition($position, $siblings->count() + 1);
            $this->validateLabel($label);
            $this->makeRoom($siblings, $position);

            $item = MenuItem::query()->create([
                'menu_id' => $menu->id,
                'language_id' => $language->id,
                'parent_id' => $parent?->id,
                'page_id' => $pageId,
                'label' => $label,
                'position' => $position,
            ]);
            $this->audit->record('menu.item.create', 'menu_item', $item->id, null, $this->itemState($item));

            return $item;
        });
    }

    public function updateItem(int $itemId, int $pageId, string $label): MenuItem
    {
        return $this->transaction(function () use ($itemId, $pageId, $label) {
            $item = $this->item($itemId);
            $this->lockMenu($item->menu_id);
            $this->validateLabel($label);
            $before = $this->itemState($item);
            if ($item->page_id !== $pageId || $item->label !== $label) {
                $item->update(['page_id' => $pageId, 'label' => $label]);
                $this->audit->record('menu.item.update', 'menu_item', $item->id, $before, $this->itemState($item->fresh()));
            }

            return $item;
        });
    }

    public function moveItem(int $itemId, int $position, ?int $parentId = null): MenuItem
    {
        return $this->transaction(function () use ($itemId, $position, $parentId) {
            $item = $this->item($itemId);
            $menu = $this->lockMenu($item->menu_id);
            $language = Language::query()->lockForUpdate()->findOrFail($item->language_id);
            $parent = $this->parent($parentId, $menu, $language);
            if ($parent !== null && ($parent->id === $item->id || $this->isDescendant($parent, $item->id))) {
                throw new NavigationOperationException('A menu item cannot be moved below itself or its descendants.');
            }

            $origin = $this->siblings($menu->id, $language->id, $item->parent_id)->reject(fn (MenuItem $sibling) => $sibling->id === $item->id)->values();
            $destination = $item->parent_id === $parent?->id
                ? $origin
                : $this->siblings($menu->id, $language->id, $parent?->id);
            $this->validatePosition($position, $destination->count() + 1);
            $before = $this->itemState($item);
            $this->closeGap($origin);
            $this->makeRoom($destination, $position);
            if ($item->parent_id !== $parent?->id || $item->position !== $position) {
                $item->update(['parent_id' => $parent?->id, 'position' => $position]);
                $this->audit->record('menu.item.move', 'menu_item', $item->id, $before, $this->itemState($item->fresh()));
            }

            return $item;
        });
    }

    public function removeItem(int $itemId): void
    {
        $this->transaction(function () use ($itemId) {
            $item = $this->item($itemId);
            $this->lockMenu($item->menu_id);
            if (MenuItem::query()->where('parent_id', $item->id)->lockForUpdate()->exists()) {
                throw new NavigationOperationException('A menu item with children cannot be removed.');
            }
            $siblings = $this->siblings($item->menu_id, $item->language_id, $item->parent_id)->reject(fn (MenuItem $sibling) => $sibling->id === $item->id)->values();
            $before = $this->itemState($item);
            $item->delete();
            $this->closeGap($siblings);
            $this->audit->record('menu.item.remove', 'menu_item', $item->id, $before, null);
        });
    }

    private function transaction(callable $operation): mixed
    {
        try {
            return DB::transaction($operation, attempts: 5);
        } catch (QueryException $exception) {
            if (str_starts_with((string) $exception->getCode(), '23')) {
                throw new NavigationOperationException('The menu item references unavailable data.', previous: $exception);
            }

            throw $exception;
        }
    }

    private function menu(string $identifier): Menu
    {
        $menu = Menu::query()->where('identifier', $identifier)->lockForUpdate()->first();
        if ($menu === null) {
            throw new NavigationOperationException("Menu [$identifier] is not available.");
        }

        return $menu;
    }

    private function lockMenu(int $menuId): Menu
    {
        return Menu::query()->lockForUpdate()->findOrFail($menuId);
    }

    private function item(int $itemId): MenuItem
    {
        $item = MenuItem::query()->lockForUpdate()->find($itemId);
        if ($item === null) {
            throw new NavigationOperationException("Menu item [$itemId] is not available.");
        }

        return $item;
    }

    private function language(string $locale): Language
    {
        $language = Language::query()->where('locale', $locale)->lockForUpdate()->first();
        if ($language === null) {
            throw new NavigationOperationException("Language [$locale] is not installed.");
        }

        return $language;
    }

    private function parent(?int $parentId, Menu $menu, Language $language): ?MenuItem
    {
        if ($parentId === null) {
            return null;
        }
        $parent = $this->item($parentId);
        if ($parent->menu_id !== $menu->id || $parent->language_id !== $language->id) {
            throw new NavigationOperationException('The parent item must belong to the same menu and language.');
        }

        return $parent;
    }

    /** @return Collection<int, MenuItem> */
    private function siblings(int $menuId, int $languageId, ?int $parentId): Collection
    {
        return MenuItem::query()->where('menu_id', $menuId)->where('language_id', $languageId)
            ->where('parent_id', $parentId)->orderBy('position')->lockForUpdate()->get();
    }

    private function validateLabel(string $label): void
    {
        if ($label === '' || mb_strlen($label, 'UTF-8') > 255 || preg_match('/[\x00-\x1F\x7F]/', $label)) {
            throw new NavigationOperationException('The menu item label is invalid.');
        }
    }

    private function validatePosition(int $position, int $maximum): void
    {
        if ($position < 1 || $position > $maximum) {
            throw new NavigationOperationException('The menu item position is outside the allowed range.');
        }
    }

    /** @param Collection<int, MenuItem> $siblings */
    private function makeRoom(Collection $siblings, int $position): void
    {
        foreach ($siblings->reverse() as $sibling) {
            if ($sibling->position >= $position) {
                $sibling->update(['position' => $sibling->position + 1]);
            }
        }
    }

    /** @param Collection<int, MenuItem> $siblings */
    private function closeGap(Collection $siblings): void
    {
        foreach ($siblings->values() as $index => $sibling) {
            $sibling->update(['position' => $index + 1]);
        }
    }

    private function isDescendant(MenuItem $item, int $ancestorId): bool
    {
        while ($item->parent_id !== null) {
            if ($item->parent_id === $ancestorId) {
                return true;
            }
            $item = $this->item($item->parent_id);
        }

        return false;
    }

    /** @return array<string, int|string> */
    private function menuState(Menu $menu): array
    {
        return ['menu_id' => $menu->id, 'identifier' => $menu->identifier];
    }

    /** @return array<string, int|string|null> */
    private function itemState(MenuItem $item): array
    {
        return [
            'menu_item_id' => $item->id,
            'menu_id' => $item->menu_id,
            'language_id' => $item->language_id,
            'parent_id' => $item->parent_id,
            'page_id' => $item->page_id,
            'label' => $item->label,
            'position' => $item->position,
        ];
    }
}
