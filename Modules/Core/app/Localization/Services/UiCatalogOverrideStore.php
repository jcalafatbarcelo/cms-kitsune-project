<?php

namespace Modules\Core\Localization\Services;

use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;
use Throwable;

class UiCatalogOverrideStore
{
    private bool $warned = false;

    public function __construct(private readonly LoggerInterface $logger) {}

    /** @return array{enabled: bool, version: int}|null */
    public function state(): ?array
    {
        try {
            $row = DB::table('ui_catalog_override_state')->where('id', 1)->first();
        } catch (Throwable $exception) {
            $this->warnUnavailable($exception);

            return null;
        }

        if ($row === null) {
            $this->warnUnavailable();

            return null;
        }

        return ['enabled' => (bool) $row->enabled, 'version' => (int) $row->version];
    }

    /** @return list<array{key: string, value: string}>|null */
    public function rowsForLocale(string $locale): ?array
    {
        try {
            return DB::table('ui_catalog_overrides')
                ->where('locale', $locale)
                ->orderBy('key')
                ->get(['key', 'value'])
                ->map(fn ($row) => ['key' => (string) $row->key, 'value' => (string) $row->value])
                ->all();
        } catch (Throwable $exception) {
            $this->warnUnavailable($exception);

            return null;
        }
    }

    private function warnUnavailable(?Throwable $exception = null): void
    {
        if ($this->warned) {
            return;
        }

        $this->warned = true;
        $this->logger->warning('UI catalog override storage is unavailable.', array_filter([
            'reason' => $exception === null ? null : $exception::class,
        ]));
    }
}
