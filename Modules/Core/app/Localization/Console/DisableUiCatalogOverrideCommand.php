<?php

namespace Modules\Core\Localization\Console;

class DisableUiCatalogOverrideCommand extends ToggleUiCatalogOverrideCommand
{
    protected $signature = 'cms:ui-catalog:override:disable';

    protected $description = 'Disable the global UI catalog override layer without deleting rows';

    protected function enabled(): bool
    {
        return false;
    }
}
