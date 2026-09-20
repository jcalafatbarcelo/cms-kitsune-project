<?php

namespace Modules\Core\Language\Console;

use Modules\Core\Language\Services\LanguageManager;

class ActivateLanguageCommand extends LanguageCommand
{
    protected $signature = 'cms:language:activate {locale}';

    protected $description = 'Activate an installed CMS language';

    public function handle(LanguageManager $languages): int
    {
        return $this->runSafely(function () use ($languages) {
            $language = $languages->activate((string) $this->argument('locale'));
            $this->components->info("Language [{$language->locale}] activated.");
        });
    }
}
