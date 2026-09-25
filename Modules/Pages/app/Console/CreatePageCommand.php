<?php

namespace Modules\Pages\Console;

use Modules\Pages\Services\PageManager;

class CreatePageCommand extends PageCommand
{
    protected $signature = 'cms:page:create {locale} {slug} {title} {--parent=} {--template=}';

    public function handle(PageManager $pages): int
    {
        return $this->runSafely(function () use ($pages) {
            $translation = $pages->create($this->argument('locale'), $this->argument('slug'), $this->argument('title'), $this->option('parent') === null ? null : (int) $this->option('parent'), $this->option('template'));
            $this->components->info("Page [{$translation->page_id}] created.");
        });
    }
}
