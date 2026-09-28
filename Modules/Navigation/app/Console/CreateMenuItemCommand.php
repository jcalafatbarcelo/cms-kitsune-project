<?php

namespace Modules\Navigation\Console;

use Modules\Navigation\Services\MenuManager;

class CreateMenuItemCommand extends NavigationCommand
{
    protected $signature = 'cms:menu:item:create {menu} {locale} {page} {label} {--parent=} {--position=}';

    public function handle(MenuManager $menus): int
    {
        return $this->runSafely(function () use ($menus) {
            $item = $menus->createItem((string) $this->argument('menu'), (string) $this->argument('locale'), (int) $this->argument('page'), (string) $this->argument('label'), $this->option('parent') === null ? null : (int) $this->option('parent'), $this->option('position') === null ? null : (int) $this->option('position'));
            $this->components->info("Menu item [{$item->id}] created.");
        });
    }
}
