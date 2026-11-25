<?php

namespace Modules\Core\Providers;

use Illuminate\Contracts\Auth\Factory;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Language\Console\ActivateLanguageCommand;
use Modules\Core\Language\Console\DisableLanguageCommand;
use Modules\Core\Language\Console\InstallLanguageCommand;
use Modules\Core\Language\Console\LanguageListCommand;
use Modules\Core\Language\Console\SetDefaultLanguageCommand;
use Modules\Core\Language\Console\SetUrlGeneralLanguageCommand;
use Modules\Core\Language\Console\ValidateLanguageCommand;
use Modules\Core\Language\Services\LanguageManager;
use Modules\Core\Localization\Console\DisableUiCatalogOverrideCommand;
use Modules\Core\Localization\Console\EnableUiCatalogOverrideCommand;
use Modules\Core\Localization\Console\ListUiCatalogOverrideCommand;
use Modules\Core\Localization\Console\RemoveUiCatalogOverrideCommand;
use Modules\Core\Localization\Console\SetUiCatalogOverrideCommand;
use Modules\Core\Localization\Console\ValidateUiCatalogOverrideCommand;
use Modules\Core\Localization\Services\UiCatalogActorResolver;
use Modules\Core\Localization\Services\UiCatalogOverrideManager;
use Modules\Core\Localization\Services\UiCatalogOverrides;
use Modules\Core\Localization\Services\UiCatalogOverrideStore;
use Modules\Core\Localization\Services\UiCatalogOverrideValidator;
use Modules\Core\Localization\Services\UiCatalogOwnerRegistry;
use Modules\Core\Localization\Services\UiCatalogOwnerResolver;
use Modules\Core\Localization\Services\UiCatalogRepository;
use Modules\Core\Localization\Services\UiCatalogResolver;
use Modules\Core\Template\Console\ActivateTemplateCommand;
use Modules\Core\Template\Console\DisableTemplateCommand;
use Modules\Core\Template\Console\SetDefaultTemplateCommand;
use Modules\Core\Template\Console\TemplateListCommand;
use Modules\Core\Template\Console\TemplateSyncCommand;
use Modules\Core\Template\Services\TemplateManager;
use Modules\Core\Template\Services\TemplatePresentationResolver;
use Modules\Core\Template\Services\TemplateUiCatalogs;
use Modules\Core\View\Components\CmsNavigation;
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
        $this->app->singleton(UiCatalogOwnerResolver::class, fn () => new UiCatalogOwnerResolver(
            (string) config('core.catalog_path'),
            base_path('Templates'),
        ));
        $this->app->singleton(
            UiCatalogOverrideValidator::class,
            fn ($app) => new UiCatalogOverrideValidator($app->make(UiCatalogOwnerResolver::class)),
        );
        $this->app->singleton(
            UiCatalogOverrideStore::class,
            fn ($app) => new UiCatalogOverrideStore($app->make(LoggerInterface::class)),
        );
        $this->app->singleton(
            UiCatalogOverrides::class,
            fn ($app) => new UiCatalogOverrides(
                $app->make(UiCatalogOverrideStore::class),
                $app->make(UiCatalogOverrideValidator::class),
                $app->make(LoggerInterface::class),
            ),
        );
        $this->app->singleton(
            UiCatalogActorResolver::class,
            fn ($app) => new UiCatalogActorResolver($app->make(Factory::class)),
        );
        $this->app->singleton(
            UiCatalogOverrideManager::class,
            fn ($app) => new UiCatalogOverrideManager(
                $app->make(UiCatalogOverrideValidator::class),
                $app->make(UiCatalogActorResolver::class),
                $app->make(UiCatalogOverrides::class),
            ),
        );
        $this->app->singleton(
            UiCatalogResolver::class,
            fn ($app) => new UiCatalogResolver(
                $app->make(UiCatalogRepository::class),
                (string) config('core.ui_catalog_fallback_mode'),
                $app->make(LoggerInterface::class),
                overrides: $app->make(UiCatalogOverrides::class),
            ),
        );
        $this->app->singleton(
            LanguageManager::class,
            fn ($app) => new LanguageManager($app->make(UiCatalogRepository::class)),
        );
        $this->app->singleton(TemplateManager::class, fn () => new TemplateManager(base_path('Templates')));
        $this->app->singleton(TemplatePresentationResolver::class, fn () => new TemplatePresentationResolver(base_path('Templates')));
        $this->app->singleton(TemplateUiCatalogs::class, fn ($app) => new TemplateUiCatalogs(
            base_path('Templates'),
            $app->make(LoggerInterface::class),
            $app->make(UiCatalogOverrides::class),
        ));
    }

    public function boot(): void
    {
        $this->app->make(UiCatalogResolver::class);
        $this->loadViewsFrom(base_path('Templates/Base/Resources/views'), 'base');
        Blade::component(CmsNavigation::class, 'cms-navigation');
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->commands([
            LanguageListCommand::class,
            InstallLanguageCommand::class,
            ValidateLanguageCommand::class,
            ActivateLanguageCommand::class,
            DisableLanguageCommand::class,
            SetDefaultLanguageCommand::class,
            SetUrlGeneralLanguageCommand::class,
            TemplateListCommand::class,
            TemplateSyncCommand::class,
            ActivateTemplateCommand::class,
            DisableTemplateCommand::class,
            SetDefaultTemplateCommand::class,
            SetUiCatalogOverrideCommand::class,
            RemoveUiCatalogOverrideCommand::class,
            ListUiCatalogOverrideCommand::class,
            EnableUiCatalogOverrideCommand::class,
            DisableUiCatalogOverrideCommand::class,
            ValidateUiCatalogOverrideCommand::class,
        ]);
    }
}
