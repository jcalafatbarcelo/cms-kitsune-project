<?php

namespace Modules\Navigation\Services;

use Modules\Navigation\Models\MenuItemHome;
use Modules\Pages\Contracts\HomePageLocator;

class HomePageResolver implements HomePageLocator
{
    public function homePageIds(): array
    {
        return MenuItemHome::query()->with('menuItem:id,page_id')->get()
            ->mapWithKeys(fn (MenuItemHome $home) => [$home->language_id => $home->menuItem->page_id])
            ->all();
    }
}
