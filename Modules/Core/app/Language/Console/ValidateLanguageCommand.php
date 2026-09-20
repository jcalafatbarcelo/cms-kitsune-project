<?php

namespace Modules\Core\Language\Console;

use Modules\Core\Language\Services\LanguageManager;

class ValidateLanguageCommand extends LanguageCommand
{
    protected $signature = 'cms:language:validate {locale?}';

    protected $description = 'Validate deployed UI catalogs for installed languages';

    public function handle(LanguageManager $languages): int
    {
        return $this->runSafely(function () use ($languages) {
            $hashes = $languages->validate($this->argument('locale'));

            $this->table(
                ['Locale', 'SHA-256'],
                collect($hashes)->map(fn ($hash, $locale) => [$locale, $hash])->values()->all(),
            );
        });
    }
}
