<?php

namespace Modules\Core\Language\Console;

use Modules\Core\Language\Services\LanguageManager;

class SetUrlGeneralLanguageCommand extends LanguageCommand
{
    protected $signature = 'cms:language:set-url-general {locale}';

    protected $description = 'Set the active URL-general language for a family';

    public function handle(LanguageManager $languages): int
    {
        return $this->runSafely(function () use ($languages) {
            $language = $languages->setUrlGeneral((string) $this->argument('locale'));
            $this->components->info("Language [{$language->locale}] is URL general.");
        });
    }
}
