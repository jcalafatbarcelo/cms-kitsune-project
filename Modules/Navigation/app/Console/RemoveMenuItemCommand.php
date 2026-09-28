<?php

namespace Modules\Navigation\Console;

use Modules\Navigation\Services\MenuManager;

class RemoveMenuItemCommand extends NavigationCommand
{
    protected $signature = 'cms:menu:item:remove {item}';

    public function handle(MenuManager $menus): int
    {
        return $this->runSafely(function () use ($menus) {
            $menus->removeItem((int) $this->argument('item'));
            $this->components->info("Menu item [{$this->argument('item')}] removed.");
        });
    }
}
