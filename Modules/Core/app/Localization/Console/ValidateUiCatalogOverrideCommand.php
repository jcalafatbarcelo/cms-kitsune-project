<?php

namespace Modules\Core\Localization\Console;

use InvalidArgumentException;
use Modules\Core\Localization\Exceptions\CatalogValidationException;
use Modules\Core\Localization\Exceptions\UiCatalogOverrideException;
use Modules\Core\Localization\Services\UiCatalogOverrideManager;

class ValidateUiCatalogOverrideCommand extends UiCatalogOverrideCommand
{
    private const REASONS = [
        'key_format' => 'invalid key',
        'owner' => 'owner unavailable',
        'key' => 'key missing from owner en catalog',
        'value' => 'invalid value',
        'compatibility' => 'placeholders or pluralization differ',
    ];

    protected $signature = 'cms:ui-catalog:override:validate';

    protected $description = 'Validate stored UI catalog overrides against current catalogs';

    public function handle(UiCatalogOverrideManager $overrides): int
    {
        try {
            $issues = $overrides->validate();
        } catch (CatalogValidationException|UiCatalogOverrideException|InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($issues === []) {
            $this->components->info('All UI catalog overrides are valid.');

            return self::SUCCESS;
        }

        $this->table(
            ['Locale', 'Owner', 'Key', 'Reason'],
            array_map(fn ($issue) => [
                $issue['locale'],
                $issue['owner'] ?? 'unknown',
                $issue['key'],
                self::REASONS[$issue['reason']] ?? $issue['reason'],
            ], $issues),
        );

        $this->components->error(count($issues).' UI catalog override(s) are invalid.');

        return self::FAILURE;
    }
}
