<?php

namespace Modules\Pages\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Pages\Console\CreatePageCommand;
use Modules\Pages\Console\PublishPageCommand;
use Modules\Pages\Console\SetHomeCommand;
use Modules\Pages\Console\TranslatePageCommand;
use Modules\Pages\Console\UnpublishPageCommand;
use Modules\Pages\Services\PageManager;

class PagesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PageManager::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->commands([CreatePageCommand::class, TranslatePageCommand::class, PublishPageCommand::class, UnpublishPageCommand::class, SetHomeCommand::class]);
    }
}
