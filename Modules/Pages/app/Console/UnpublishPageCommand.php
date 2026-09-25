<?php

namespace Modules\Pages\Console;

use Modules\Pages\Services\PageManager;

class UnpublishPageCommand extends PageCommand
{
    protected $signature = 'cms:page:unpublish {page} {locale}';

    public function handle(PageManager $pages): int
    {
        return $this->runSafely(function () use ($pages) {
            $pages->unpublish((int) $this->argument('page'), $this->argument('locale'));
            $this->components->info('Page unpublished.');
        });
    }
}
