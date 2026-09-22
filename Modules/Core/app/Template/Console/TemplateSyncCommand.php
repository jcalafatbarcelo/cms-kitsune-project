<?php

namespace Modules\Core\Template\Console;

use Modules\Core\Template\Services\TemplateManager;

class TemplateSyncCommand extends TemplateCommand
{
    protected $signature = 'cms:template:sync';

    protected $description = 'Synchronize deployed CMS Templates';

    public function handle(TemplateManager $templates): int
    {
        return $this->runSafely(function () use ($templates) {
            $templates->sync();
            $this->components->info('CMS Templates synchronized.');
        });
    }
}
