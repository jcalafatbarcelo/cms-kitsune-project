<?php

namespace Modules\Core\Template\Console;

use Illuminate\Console\Command;
use Modules\Core\Template\Exceptions\TemplateOperationException;

abstract class TemplateCommand extends Command
{
    protected function runSafely(callable $operation): int
    {
        try {
            $operation();

            return self::SUCCESS;
        } catch (TemplateOperationException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
