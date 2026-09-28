<?php

namespace Modules\Navigation\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Modules\Navigation\Console\CreateMenuCommand;
use Modules\Navigation\Console\CreateMenuItemCommand;
use Modules\Navigation\Console\MoveMenuItemCommand;
use Modules\Navigation\Console\RemoveMenuItemCommand;
use Modules\Navigation\Console\UpdateMenuItemCommand;
use Modules\Navigation\Services\MenuManager;
use Modules\Navigation\Services\PublicMenuResolver;
use Modules\Navigation\View\Components\NavigationMenu;

class NavigationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MenuManager::class);
        $this->app->singleton(PublicMenuResolver::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'navigation');
        Blade::component(NavigationMenu::class, 'navigation-menu');
        $this->commands([CreateMenuCommand::class, CreateMenuItemCommand::class, UpdateMenuItemCommand::class, MoveMenuItemCommand::class, RemoveMenuItemCommand::class]);
    }
}
