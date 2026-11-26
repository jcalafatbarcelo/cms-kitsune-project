<?php

namespace Modules\Core\Localization\Console;

use Modules\Core\Localization\Services\UiCatalogOverrideManager;

class RemoveUiCatalogOverrideCommand extends UiCatalogOverrideCommand
{
    protected $signature = 'cms:ui-catalog:override:remove {key} {locale}';

    protected $description = 'Remove a UI catalog override';

    public function handle(UiCatalogOverrideManager $overrides): int
    {
        return $this->runSafely(function () use ($overrides) {
            $key = (string) $this->argument('key');
            $locale = (string) $this->argument('locale');
            $overrides->remove($key, $locale);

            $this->components->info("Override [$key] for [$locale] removed.");
        });
    }
}
