<?php

namespace Modules\Pages\Console;

use Modules\Pages\Services\PageManager;

class SetHomeCommand extends PageCommand
{
    protected $signature = 'cms:page:set-home {page} {locale}';

    public function handle(PageManager $pages): int
    {
        return $this->runSafely(function () use ($pages) {
            $pages->setHome((int) $this->argument('page'), $this->argument('locale'));
            $this->components->info('Home page updated.');
        });
    }
}
