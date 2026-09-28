<?php

namespace Modules\Navigation\Console;

use Illuminate\Console\Command;
use Modules\Navigation\Exceptions\NavigationOperationException;

abstract class NavigationCommand extends Command
{
    protected function runSafely(callable $operation): int
    {
        try {
            $operation();

            return self::SUCCESS;
        } catch (NavigationOperationException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
