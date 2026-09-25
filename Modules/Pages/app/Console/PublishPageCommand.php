<?php

namespace Modules\Pages\Console;

use Modules\Pages\Services\PageManager;

class PublishPageCommand extends PageCommand
{
    protected $signature = 'cms:page:publish {page} {locale}';

    public function handle(PageManager $pages): int
    {
        return $this->runSafely(function () use ($pages) {
            $pages->publish((int) $this->argument('page'), $this->argument('locale'));
            $this->components->info('Page published.');
        });
    }
}
