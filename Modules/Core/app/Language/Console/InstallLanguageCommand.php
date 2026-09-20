<?php

namespace Modules\Core\Language\Console;

use Modules\Core\Language\Services\LanguageManager;

class InstallLanguageCommand extends LanguageCommand
{
    protected $signature = 'cms:language:install {manifest}';

    protected $description = 'Install a CMS language from a local manifest';

    public function handle(LanguageManager $languages): int
    {
        return $this->runSafely(function () use ($languages) {
            $language = $languages->install((string) $this->argument('manifest'));
            $this->components->info("Language [{$language->locale}] installed as inactive.");
        });
    }
}
