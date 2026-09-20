<?php

namespace Modules\Core\Language\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Modules\Core\Language\Exceptions\LanguageOperationException;
use Modules\Core\Localization\Exceptions\CatalogValidationException;

abstract class LanguageCommand extends Command
{
    protected function runSafely(callable $operation): int
    {
        try {
            $operation();

            return self::SUCCESS;
        } catch (CatalogValidationException|LanguageOperationException|InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
