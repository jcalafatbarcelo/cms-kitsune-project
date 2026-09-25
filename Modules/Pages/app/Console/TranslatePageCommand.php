<?php

namespace Modules\Pages\Console;

use Modules\Pages\Services\PageManager;

class TranslatePageCommand extends PageCommand
{
    protected $signature = 'cms:page:translate {page} {locale} {slug} {title}';

    public function handle(PageManager $pages): int
    {
        return $this->runSafely(function () use ($pages) {
            $pages->translate((int) $this->argument('page'), $this->argument('locale'), $this->argument('slug'), $this->argument('title'));
            $this->components->info('Page translated.');
        });
    }
}
