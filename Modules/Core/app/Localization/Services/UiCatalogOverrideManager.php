<?php

namespace Modules\Core\Localization\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Core\Localization\Exceptions\UiCatalogOverrideException;
use Modules\Core\Localization\Models\UiCatalogOverride;
use Modules\Core\Localization\Models\UiCatalogOverrideState;

class UiCatalogOverrideManager
{
    public function __construct(
        private readonly UiCatalogOverrideValidator $validator,
        private readonly UiCatalogActorResolver $actors,
        private readonly UiCatalogOverrides $overrides,
    ) {}

    /** @return array{created: bool, override: UiCatalogOverride} */
    public function set(string $key, string $locale, string $value): array
    {
        $actor = $this->actors->current();
        $this->validator->assertLocale($locale);
        $this->validator->assertWritable($key, $value);

        $result = $this->transaction(function () use ($key, $locale, $value, $actor) {
            $state = $this->lockedState();
            $override = UiCatalogOverride::query()
                ->where('locale', $locale)
                ->where('key', $key)
                ->lockForUpdate()
                ->first();

            if ($override === null) {
                $override = UiCatalogOverride::query()->create([
                    'locale' => $locale,
                    'key' => $key,
                    'value' => $value,
                    'created_by' => $actor,
                    'updated_by' => $actor,
                ]);
                $created = true;
            } else {
                $override->update(['value' => $value, 'updated_by' => $actor]);
                $created = false;
            }

            $this->incrementVersion($state);

            return ['created' => $created, 'override' => $override->refresh()];
        });

        $this->overrides->forget();

        return $result;
    }

    public function remove(string $key, string $locale): void
    {
        $this->validator->assertRemovableKey($key);
        $this->validator->assertLocale($locale);

        $this->transaction(function () use ($key, $locale) {
            $state = $this->lockedState();
            // Resolve the actor to enforce web authorization even though removal stores no actor.
            $this->actors->current();
            $override = UiCatalogOverride::query()
                ->where('locale', $locale)
                ->where('key', $key)
                ->lockForUpdate()
                ->first();

            if ($override === null) {
                throw new UiCatalogOverrideException("Override [$key] for [$locale] does not exist.");
            }

            $override->delete();
            $this->incrementVersion($state);
        });

        $this->overrides->forget();
    }

    /** @return array{changed: bool, enabled: bool, version: int} */
    public function toggle(bool $enabled): array
    {
        $result = $this->transaction(function () use ($enabled) {
            $state = $this->lockedState();
            $actor = $this->actors->current();

            if ($state->enabled === $enabled) {
                return ['changed' => false, 'enabled' => $state->enabled, 'version' => $state->version];
            }

            $state->update([
                'enabled' => $enabled,
                'updated_by' => $actor,
                'version' => $state->version + 1,
            ]);

            return ['changed' => true, 'enabled' => $state->enabled, 'version' => $state->version];
        });

        if ($result['changed']) {
            $this->overrides->forget();
        }

        return $result;
    }

    /** @return array{enabled: bool, version: int} */
    public function state(): array
    {
        try {
            $state = UiCatalogOverrideState::query()->find(1);
        } catch (QueryException $exception) {
            throw new UiCatalogOverrideException('UI catalog override storage is unavailable.', previous: $exception);
        }

        if ($state === null) {
            throw new UiCatalogOverrideException('UI catalog override state is unavailable.');
        }

        return ['enabled' => $state->enabled, 'version' => $state->version];
    }

    /** @return list<array{locale: string, key: string, owner: string|null, reason: string}> */
    public function validate(): array
    {
        $issues = [];

        foreach ($this->rows() as $row) {
            $reason = $this->validator->inspect($row->key, $row->value);

            if ($reason !== null) {
                $issues[] = [
                    'locale' => $row->locale,
                    'key' => $row->key,
                    'owner' => $this->validator->ownerOf($row->key),
                    'reason' => $reason,
                ];
            }
        }

        return $issues;
    }

    /** @return Collection<int, UiCatalogOverride> */
    public function entries(?string $locale = null, ?string $owner = null): Collection
    {
        if ($locale !== null) {
            UiCatalogRepository::assertLocale($locale);
        }

        if ($owner !== null && preg_match('/^[a-z][a-z0-9-]*$/D', $owner) !== 1) {
            throw new UiCatalogOverrideException('The UI catalog owner filter is invalid.');
        }

        $query = UiCatalogOverride::query()->orderBy('locale')->orderBy('key');

        if ($locale !== null) {
            $query->where('locale', $locale);
        }

        if ($owner !== null) {
            $query->where('key', 'like', $owner.'::%');
        }

        return $this->rows($query);
    }

    /**
     * @param  Builder<UiCatalogOverride>|null  $query
     * @return Collection<int, UiCatalogOverride>
     */
    private function rows(?Builder $query = null): Collection
    {
        try {
            return ($query ?? UiCatalogOverride::query()->orderBy('locale')->orderBy('key'))->get();
        } catch (QueryException $exception) {
            throw new UiCatalogOverrideException('UI catalog override storage is unavailable.', previous: $exception);
        }
    }

    private function lockedState(): UiCatalogOverrideState
    {
        $state = UiCatalogOverrideState::query()->lockForUpdate()->find(1);

        if ($state === null) {
            throw new UiCatalogOverrideException('UI catalog override state is unavailable.');
        }

        return $state;
    }

    private function incrementVersion(UiCatalogOverrideState $state): void
    {
        $state->update(['version' => $state->version + 1]);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function transaction(callable $callback)
    {
        try {
            return DB::transaction($callback, attempts: 5);
        } catch (QueryException $exception) {
            throw new UiCatalogOverrideException('UI catalog override storage is unavailable.', previous: $exception);
        }
    }
}
