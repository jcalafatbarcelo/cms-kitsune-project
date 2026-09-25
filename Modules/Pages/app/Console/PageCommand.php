<?php

namespace Modules\Pages\Console;

use Illuminate\Console\Command;
use Modules\Core\Localization\Exceptions\CatalogValidationException;
use Modules\Core\Template\Exceptions\TemplateOperationException;
use Modules\Pages\Exceptions\PageOperationException;

abstract class PageCommand extends Command
{
    protected function runSafely(callable $operation): int
    {
        try {
            $operation();

            return self::SUCCESS;
        } catch (PageOperationException|TemplateOperationException|CatalogValidationException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
