<?php

namespace Modules\Core\Localization\Console;

class EnableUiCatalogOverrideCommand extends ToggleUiCatalogOverrideCommand
{
    protected $signature = 'cms:ui-catalog:override:enable';

    protected $description = 'Enable the global UI catalog override layer';

    protected function enabled(): bool
    {
        return true;
    }
}
