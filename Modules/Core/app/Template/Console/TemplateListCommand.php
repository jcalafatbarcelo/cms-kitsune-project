<?php

namespace Modules\Core\Template\Console;

use Modules\Core\Template\Models\CmsTemplateSetting;
use Modules\Core\Template\Services\TemplateManager;

class TemplateListCommand extends TemplateCommand
{
    protected $signature = 'cms:template:list';

    protected $description = 'List registered CMS Templates';

    public function handle(TemplateManager $templates): int
    {
        $defaultId = CmsTemplateSetting::query()->findOrFail(1)->default_template_id;
        $this->table(['Identifier', 'Name', 'Active', 'Default'], $templates->all()->map(fn ($template) => [
            $template->identifier, $template->name, $template->is_active ? 'yes' : 'no', $template->id === $defaultId ? 'yes' : 'no',
        ])->all());

        return self::SUCCESS;
    }
}
