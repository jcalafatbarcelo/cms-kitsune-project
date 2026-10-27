<?php

namespace Modules\TinyMCE\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Pages\Services\ContentEditorRegistry;
use Modules\TinyMCE\Services\TinymceEditor;

class TinyMCEServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->app->make(ContentEditorRegistry::class)->register(new TinymceEditor);
    }
}
