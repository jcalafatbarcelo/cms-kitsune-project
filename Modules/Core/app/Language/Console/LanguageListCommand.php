<?php

namespace Modules\Core\Language\Console;

use Modules\Core\Language\Services\LanguageManager;

class LanguageListCommand extends LanguageCommand
{
    protected $signature = 'cms:language:list';

    protected $description = 'List installed CMS languages';

    public function handle(LanguageManager $languages): int
    {
        $this->table(
            ['Locale', 'Name', 'Native name', 'Direction', 'Active'],
            $languages->all()->map(fn ($language) => [
                $language->locale,
                $language->name,
                $language->native_name,
                $language->text_direction,
                $language->is_active ? 'yes' : 'no',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
