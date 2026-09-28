<?php

namespace Modules\Navigation\Services;

use Illuminate\Support\Collection;
use Modules\Core\Language\Models\Language;
use Modules\Navigation\Exceptions\NavigationOperationException;
use Modules\Navigation\Models\Menu;
use Modules\Navigation\Models\MenuItem;
use Modules\Pages\Services\PublicPageUrlResolver;

class PublicMenuResolver
{
    public function __construct(private readonly PublicPageUrlResolver $pages) {}

    /** @return array<int, array{id: int, label: string, url: string, children: array}> */
    public function forMenu(string $identifier, string $locale): array
    {
        $language = Language::query()->where('locale', $locale)->where('is_active', true)->first();
        if ($language === null) {
            throw new NavigationOperationException("Language [$locale] is not active.");
        }
        $menu = Menu::query()->where('identifier', $identifier)->first();
        if ($menu === null) {
            return [];
        }
        $menuItems = MenuItem::query()->where('menu_id', $menu->id)->where('language_id', $language->id)->orderBy('position')->get();
        $urls = $this->pages->forPages($menuItems->pluck('page_id')->unique()->values()->all(), $locale);
        $items = $menuItems->groupBy('parent_id');

        return $this->children($items, null, $urls);
    }

    /** @param Collection<int|string, Collection<int, MenuItem>> $items
     * @return array<int, array{id: int, label: string, url: string, children: array}>
     */
    private function children($items, ?int $parentId, array $urls): array
    {
        $resolved = [];
        foreach ($items->get($parentId, collect()) as $item) {
            if (! isset($urls[$item->page_id])) {
                continue;
            }
            $resolved[] = ['id' => $item->id, 'label' => $item->label, 'url' => $urls[$item->page_id], 'children' => $this->children($items, $item->id, $urls)];
        }

        return $resolved;
    }
}
