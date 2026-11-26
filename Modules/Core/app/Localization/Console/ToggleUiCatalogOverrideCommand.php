<?php

namespace Modules\Core\Localization\Console;

use Modules\Core\Localization\Services\UiCatalogOverrideManager;

abstract class ToggleUiCatalogOverrideCommand extends UiCatalogOverrideCommand
{
    abstract protected function enabled(): bool;

    public function handle(UiCatalogOverrideManager $overrides): int
    {
        return $this->runSafely(function () use ($overrides) {
            $enabled = $this->enabled();
            $state = $enabled ? 'enabled' : 'disabled';
            $result = $overrides->toggle($enabled);

            if (! $result['changed']) {
                $this->components->info("UI catalog overrides are already $state.");

                return;
            }

            $this->components->info("UI catalog overrides $state. Version [{$result['version']}].");
        });
    }
}
