<?php

namespace Modules\Core\Localization\Console;

use Modules\Core\Localization\Services\UiCatalogOverrideManager;

class ListUiCatalogOverrideCommand extends UiCatalogOverrideCommand
{
    protected $signature = 'cms:ui-catalog:override:list {--locale=} {--owner=} {--with-values}';

    protected $description = 'List UI catalog override metadata';

    public function handle(UiCatalogOverrideManager $overrides): int
    {
        return $this->runSafely(function () use ($overrides) {
            $locale = $this->option('locale');
            $owner = $this->option('owner');
            $withValues = (bool) $this->option('with-values');
            $entries = $overrides->entries(
                is_string($locale) && $locale !== '' ? $locale : null,
                is_string($owner) && $owner !== '' ? $owner : null,
            );

            if ($entries->isEmpty()) {
                $this->components->info('No UI catalog overrides match the filters.');

                return;
            }

            $headers = ['Locale', 'Key', 'Created by', 'Updated by', 'Updated at'];
            $rows = $entries->map(fn ($entry) => [
                $entry->locale,
                $entry->key,
                $entry->created_by,
                $entry->updated_by,
                $entry->updated_at?->toDateTimeString() ?? '-',
            ])->all();

            if ($withValues) {
                $headers[] = 'Value';

                foreach ($entries->values() as $index => $entry) {
                    $rows[$index][] = $entry->value;
                }
            }

            $this->table($headers, $rows);
        });
    }
}
