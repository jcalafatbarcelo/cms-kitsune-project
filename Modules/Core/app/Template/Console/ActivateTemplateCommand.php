<?php

namespace Modules\Core\Template\Console;

use Modules\Core\Template\Services\TemplateManager;

class ActivateTemplateCommand extends TemplateCommand
{
    protected $signature = 'cms:template:activate {identifier}';

    protected $description = 'Activate a CMS Template';

    public function handle(TemplateManager $templates): int
    {
        return $this->runSafely(function () use ($templates) {
            $template = $templates->activate((string) $this->argument('identifier'));
            $this->components->info("Template [{$template->identifier}] activated.");
        });
    }
}
