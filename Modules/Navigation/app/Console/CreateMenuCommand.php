<?php

namespace Modules\Navigation\Console;

use Modules\Navigation\Services\MenuManager;

class CreateMenuCommand extends NavigationCommand
{
    protected $signature = 'cms:menu:create {identifier}';

    public function handle(MenuManager $menus): int
    {
        return $this->runSafely(function () use ($menus) {
            $menu = $menus->create((string) $this->argument('identifier'));
            $this->components->info("Menu [{$menu->identifier}] created.");
        });
    }
}
