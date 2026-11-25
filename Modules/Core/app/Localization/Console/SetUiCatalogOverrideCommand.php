<?php

namespace Modules\Core\Localization\Console;

use Modules\Core\Localization\Services\UiCatalogOverrideManager;

class SetUiCatalogOverrideCommand extends UiCatalogOverrideCommand
{
    protected $signature = 'cms:ui-catalog:override:set {key} {locale} {--value=}';

    protected $description = 'Create or update a UI catalog override';

    public function handle(UiCatalogOverrideManager $overrides): int
    {
        $value = $this->option('value');

        if ($value === null) {
            $this->components->error('The --value option is required.');

            return self::FAILURE;
        }

        return $this->runSafely(function () use ($overrides, $value) {
            $key = (string) $this->argument('key');
            $locale = (string) $this->argument('locale');
            $result = $overrides->set($key, $locale, (string) $value);
            $action = $result['created'] ? 'created' : 'updated';

            $this->components->info("Override [$key] for [$locale] $action.");
        });
    }
}
