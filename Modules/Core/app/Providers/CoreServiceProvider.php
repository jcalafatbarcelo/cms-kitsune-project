<?php

namespace Modules\Core\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Language\Console\ActivateLanguageCommand;
use Modules\Core\Language\Console\DisableLanguageCommand;
use Modules\Core\Language\Console\InstallLanguageCommand;
use Modules\Core\Language\Console\LanguageListCommand;
use Modules\Core\Language\Console\SetDefaultLanguageCommand;
use Modules\Core\Language\Console\ValidateLanguageCommand;
use Modules\Core\Language\Services\LanguageManager;
use Modules\Core\Localization\Services\UiCatalogOwnerRegistry;
use Modules\Core\Localization\Services\UiCatalogRepository;
use Modules\Core\Localization\Services\UiCatalogResolver;
use Modules\Core\Template\Console\ActivateTemplateCommand;
use Modules\Core\Template\Console\DisableTemplateCommand;
use Modules\Core\Template\Console\SetDefaultTemplateCommand;
use Modules\Core\Template\Console\TemplateListCommand;
use Modules\Core\Template\Console\TemplateSyncCommand;
use Modules\Core\Template\Services\TemplateManager;
use Psr\Log\LoggerInterface;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/config.php', 'core');

        $this->app->singleton(UiCatalogOwnerRegistry::class, function () {
            $registry = new UiCatalogOwnerRegistry;
            $registry->register('Core', reserved: true);

            return $registry;
        });
        $this->app->singleton(
            UiCatalogRepository::class,
            fn () => new UiCatalogRepository((string) config('core.catalog_path')),
        );
        $this->app->singleton(
            UiCatalogResolver::class,
            fn ($app) => new UiCatalogResolver(
                $app->make(UiCatalogRepository::class),
                (string) config('core.ui_catalog_fallback_mode'),
                $app->make(LoggerInterface::class),
            ),
        );
        $this->app->singleton(
            LanguageManager::class,
            fn ($app) => new LanguageManager($app->make(UiCatalogRepository::class)),
        );
        $this->app->singleton(TemplateManager::class, fn () => new TemplateManager((string) config('core.template_path')));
    }

    public function boot(): void
    {
        $this->app->make(UiCatalogResolver::class);
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->commands([
            LanguageListCommand::class,
            InstallLanguageCommand::class,
            ValidateLanguageCommand::class,
            ActivateLanguageCommand::class,
            DisableLanguageCommand::class,
            SetDefaultLanguageCommand::class,
            TemplateListCommand::class,
            TemplateSyncCommand::class,
            ActivateTemplateCommand::class,
            DisableTemplateCommand::class,
            SetDefaultTemplateCommand::class,
        ]);
    }
}
