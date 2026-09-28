<?php

namespace Modules\Navigation\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Navigation\Console\CreateMenuCommand;
use Modules\Navigation\Console\CreateMenuItemCommand;
use Modules\Navigation\Console\MoveMenuItemCommand;
use Modules\Navigation\Console\RemoveMenuItemCommand;
use Modules\Navigation\Console\UpdateMenuItemCommand;
use Modules\Navigation\Services\MenuManager;
use Modules\Navigation\Services\PublicMenuResolver;

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
        $this->commands([CreateMenuCommand::class, CreateMenuItemCommand::class, UpdateMenuItemCommand::class, MoveMenuItemCommand::class, RemoveMenuItemCommand::class]);
    }
}
