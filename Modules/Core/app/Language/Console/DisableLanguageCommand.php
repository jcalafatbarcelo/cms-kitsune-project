<?php

namespace Modules\Core\Language\Console;

use Modules\Core\Language\Services\LanguageManager;

class DisableLanguageCommand extends LanguageCommand
{
    protected $signature = 'cms:language:disable {locale}';

    protected $description = 'Disable an active non-default CMS language';

    public function handle(LanguageManager $languages): int
    {
        return $this->runSafely(function () use ($languages) {
            $language = $languages->disable((string) $this->argument('locale'));
            $this->components->info("Language [{$language->locale}] disabled.");
        });
    }
}
