<?php

namespace Modules\Core\Template\Console;

use Modules\Core\Template\Services\TemplateManager;

class SetDefaultTemplateCommand extends TemplateCommand
{
    protected $signature = 'cms:template:set-default {identifier}';

    protected $description = 'Set the default CMS Template';

    public function handle(TemplateManager $templates): int
    {
        return $this->runSafely(function () use ($templates) {
            $template = $templates->setDefault((string) $this->argument('identifier'));
            $this->components->info("Template [{$template->identifier}] is now default.");
        });
    }
}
