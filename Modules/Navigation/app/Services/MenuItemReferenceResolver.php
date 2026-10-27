<?php

namespace Modules\Navigation\Services;

use Modules\Navigation\Models\MenuItem;
use Modules\Pages\Contracts\MenuReferenceResolver;

class MenuItemReferenceResolver implements MenuReferenceResolver
{
    public function referencesForPage(int $pageId): array
    {
        return MenuItem::query()->where('page_id', $pageId)->pluck('label', 'id')->all();
    }
}
