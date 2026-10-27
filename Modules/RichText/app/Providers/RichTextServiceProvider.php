<?php

namespace Modules\RichText\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Pages\Services\ContentTypeRegistry;
use Modules\RichText\RichTextType;

class RichTextServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->app->make(ContentTypeRegistry::class)->register(new RichTextType);
    }
}
