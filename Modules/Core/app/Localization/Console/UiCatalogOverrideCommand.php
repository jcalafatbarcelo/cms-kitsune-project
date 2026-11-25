<?php

namespace Modules\Core\Localization\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Modules\Core\Localization\Exceptions\CatalogValidationException;
use Modules\Core\Localization\Exceptions\UiCatalogOverrideException;

abstract class UiCatalogOverrideCommand extends Command
{
    protected function runSafely(callable $operation): int
    {
        try {
            $operation();

            return self::SUCCESS;
        } catch (CatalogValidationException|UiCatalogOverrideException|InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
