<?php

namespace Modules\Core\Template\Console;

use Modules\Core\Template\Services\TemplateManager;

class DisableTemplateCommand extends TemplateCommand
{
    protected $signature = 'cms:template:disable {identifier}';

    protected $description = 'Disable a CMS Template';

    public function handle(TemplateManager $templates): int
    {
        return $this->runSafely(function () use ($templates) {
            $template = $templates->disable((string) $this->argument('identifier'));
            $this->components->info("Template [{$template->identifier}] disabled.");
        });
    }
}
