<?php

namespace Modules\Core\Language\Console;

use Modules\Core\Language\Services\LanguageManager;

class SetDefaultLanguageCommand extends LanguageCommand
{
    protected $signature = 'cms:language:set-default {context} {locale}';

    protected $description = 'Set the frontend or backoffice default CMS language';

    public function handle(LanguageManager $languages): int
    {
        return $this->runSafely(function () use ($languages) {
            $context = (string) $this->argument('context');
            $locale = (string) $this->argument('locale');
            $languages->setDefault($context, $locale);
            $this->components->info("Default [$context] language set to [$locale].");
        });
    }
}
