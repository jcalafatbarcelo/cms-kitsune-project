<?php

namespace Modules\Navigation\Console;

use Modules\Navigation\Services\MenuManager;

class UpdateMenuItemCommand extends NavigationCommand
{
    protected $signature = 'cms:menu:item:update {item} {page} {label}';

    public function handle(MenuManager $menus): int
    {
        return $this->runSafely(function () use ($menus) {
            $menus->updateItem((int) $this->argument('item'), (int) $this->argument('page'), (string) $this->argument('label'));
            $this->components->info("Menu item [{$this->argument('item')}] updated.");
        });
    }
}
