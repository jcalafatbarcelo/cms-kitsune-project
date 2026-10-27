<?php

namespace Modules\Pages\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Language\Events\LanguageInstalled;
use Modules\Pages\Console\CreatePageCommand;
use Modules\Pages\Console\PublishPageCommand;
use Modules\Pages\Console\TranslatePageCommand;
use Modules\Pages\Console\UnpublishPageCommand;
use Modules\Pages\Services\ContentEditorRegistry;
use Modules\Pages\Services\ContentSanitizer;
use Modules\Pages\Services\ContentTypeRegistry;
use Modules\Pages\Services\EmbedHtmlRenderer;
use Modules\Pages\Services\EmbedMarkerParser;
use Modules\Pages\Services\EmbedResolver;
use Modules\Pages\Services\EmbedTypeRegistry;
use Modules\Pages\Services\PageManager;
use Modules\Pages\Services\PublicPageResolver;
use Modules\Pages\Services\PublicPageUrlResolver;
use Modules\Pages\Support\ContentTypeDefinition;
use Modules\Pages\Support\SanitizationPolicyValue;
use Modules\Pages\View\Components\CmsEmbed;

class PagesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ContentTypeRegistry::class);
        $this->app->singleton(ContentEditorRegistry::class);
        $this->app->singleton(EmbedTypeRegistry::class);
        $this->app->singleton(ContentSanitizer::class);
        $this->app->singleton(EmbedMarkerParser::class);
        $this->app->singleton(EmbedResolver::class);
        $this->app->singleton(EmbedHtmlRenderer::class);
        $this->app->singleton(PageManager::class);
        $this->app->singleton(PublicPageResolver::class);
        $this->app->singleton(PublicPageUrlResolver::class);
    }

    public function boot(): void
    {
        $this->app->make(ContentTypeRegistry::class)->register(new ContentTypeDefinition(
            identifier: 'system.static',
            name: 'Static system page',
            editor: null,
            isSystem: true,
            policy: new SanitizationPolicyValue([]),
        ));
        Blade::component(CmsEmbed::class, 'cms-embed');
        Event::listen(LanguageInstalled::class, function (LanguageInstalled $event) {
            $this->app->make(PageManager::class)->addLanguageStubs($event->language);
        });
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../../routes/admin.php');
        $this->commands([CreatePageCommand::class, TranslatePageCommand::class, PublishPageCommand::class, UnpublishPageCommand::class]);
    }
}
