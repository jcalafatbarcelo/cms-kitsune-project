<?php

namespace Modules\Navigation\Console;

use Modules\Navigation\Services\MenuManager;

class MoveMenuItemCommand extends NavigationCommand
{
    protected $signature = 'cms:menu:item:move {item} {position} {--parent=}';

    public function handle(MenuManager $menus): int
    {
        return $this->runSafely(function () use ($menus) {
            $menus->moveItem((int) $this->argument('item'), (int) $this->argument('position'), $this->option('parent') === null ? null : (int) $this->option('parent'));
            $this->components->info("Menu item [{$this->argument('item')}] moved.");
        });
    }
}
