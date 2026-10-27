<?php

namespace Modules\Navigation\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Navigation\Console\CreateMenuCommand;
use Modules\Navigation\Console\CreateMenuItemCommand;
use Modules\Navigation\Console\MoveMenuItemCommand;
use Modules\Navigation\Console\RemoveMenuItemCommand;
use Modules\Navigation\Console\UpdateMenuItemCommand;
use Modules\Navigation\Services\HomePageResolver;
use Modules\Navigation\Services\MenuItemReferenceResolver;
use Modules\Navigation\Services\MenuManager;
use Modules\Navigation\Services\PublicMenuResolver;
use Modules\Pages\Contracts\HomePageLocator;
use Modules\Pages\Contracts\MenuReferenceResolver;

class NavigationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MenuManager::class);
        $this->app->singleton(PublicMenuResolver::class);
        $this->app->singleton(HomePageResolver::class);
        $this->app->singleton(HomePageLocator::class, HomePageResolver::class);
        $this->app->singleton(MenuReferenceResolver::class, MenuItemReferenceResolver::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../../routes/admin.php');
        $this->commands([CreateMenuCommand::class, CreateMenuItemCommand::class, UpdateMenuItemCommand::class, MoveMenuItemCommand::class, RemoveMenuItemCommand::class]);
    }
}
