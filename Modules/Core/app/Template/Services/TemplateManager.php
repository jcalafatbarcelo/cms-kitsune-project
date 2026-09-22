<?php

namespace Modules\Core\Template\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Core\Template\Exceptions\TemplateOperationException;
use Modules\Core\Template\Models\CmsTemplate;
use Modules\Core\Template\Models\CmsTemplateSetting;

class TemplateManager
{
    private readonly TemplateManifestValidator $validator;

    public function __construct(string $root)
    {
        $this->validator = new TemplateManifestValidator($root);
        $this->root = $root;
    }

    private string $root;

    /** @return Collection<int, CmsTemplate> */
    public function all(): Collection
    {
        return CmsTemplate::query()->orderBy('identifier')->get();
    }

    public function sync(): void
    {
        if (! is_dir($this->root)) {
            throw new TemplateOperationException('Template root is unavailable.');
        }

        $snapshots = [];
        foreach (new \DirectoryIterator($this->root) as $entry) {
            if ($entry->isDot()) {
                continue;
            }

            if ($entry->isLink() || (! $entry->isDir() && ! $entry->isFile())) {
                throw new TemplateOperationException('Template root contains an unsafe entry.');
            }

            if (! $entry->isDir() || str_starts_with($entry->getFilename(), '.')) {
                continue;
            }

            $snapshot = $this->validator->inspect($entry->getFilename());
            if (isset($snapshots[$snapshot['directory_key']])) {
                throw new TemplateOperationException('Template directories collide by case.');
            }
            $snapshots[$snapshot['directory_key']] = $snapshot;
        }

        DB::transaction(function () use ($snapshots) {
            CmsTemplateSetting::query()->lockForUpdate()->findOrFail(1);
            $registered = CmsTemplate::query()->lockForUpdate()->get()->keyBy('directory_key');

            foreach ($registered as $key => $template) {
                $snapshot = $snapshots[$key] ?? null;
                if ($snapshot === null || $snapshot['directory'] !== $template->directory
                    || $snapshot['identifier'] !== $template->identifier) {
                    throw new TemplateOperationException("Registered template [{$template->identifier}] changed or is unavailable.");
                }
            }

            foreach ($snapshots as $snapshot) {
                $existing = $registered->get($snapshot['directory_key']);
                if ($existing !== null) {
                    $existing->update(['name' => $snapshot['name'], 'manifest_hash' => $snapshot['manifest_hash']]);

                    continue;
                }
                if (CmsTemplate::query()->where('identifier', $snapshot['identifier'])->exists()) {
                    throw new TemplateOperationException("Template identifier [{$snapshot['identifier']}] collides.");
                }
                CmsTemplate::query()->create([...$snapshot, 'is_active' => false, 'registered_at' => now()]);
            }
        }, attempts: 5);
    }

    public function activate(string $identifier): CmsTemplate
    {
        return $this->transition($identifier, function (CmsTemplate $template) {
            if ($template->is_active) {
                throw new TemplateOperationException("Template [$template->identifier] is already active.");
            }
            $template->update(['is_active' => true]);
        });
    }

    public function setDefault(string $identifier): CmsTemplate
    {
        return $this->transition($identifier, function (CmsTemplate $template, CmsTemplateSetting $settings) {
            if (! $template->is_active) {
                throw new TemplateOperationException("Inactive template [$template->identifier] cannot be default.");
            }
            $settings->update(['default_template_id' => $template->id]);
        });
    }

    public function disable(string $identifier): CmsTemplate
    {
        return DB::transaction(function () use ($identifier) {
            $settings = CmsTemplateSetting::query()->lockForUpdate()->findOrFail(1);
            $template = $this->locked($identifier);
            if ($template->identifier === 'base' || $template->id === $settings->default_template_id || ! $template->is_active) {
                throw new TemplateOperationException("Template [$identifier] cannot be disabled.");
            }
            $template->update(['is_active' => false]);

            return $template->refresh();
        }, attempts: 5);
    }

    private function transition(string $identifier, callable $operation): CmsTemplate
    {
        return DB::transaction(function () use ($identifier, $operation) {
            $settings = CmsTemplateSetting::query()->lockForUpdate()->findOrFail(1);
            $template = $this->locked($identifier);
            $snapshot = $this->validator->inspect($template->directory);
            if ($snapshot['identifier'] !== $template->identifier || $snapshot['directory_key'] !== $template->directory_key) {
                throw new TemplateOperationException("Template [$identifier] identity changed.");
            }
            $operation($template, $settings);

            return $template->refresh();
        }, attempts: 5);
    }

    private function locked(string $identifier): CmsTemplate
    {
        $template = CmsTemplate::query()->where('identifier', $identifier)->lockForUpdate()->first();
        if ($template === null) {
            throw new TemplateOperationException("Template [$identifier] is not registered.");
        }

        return $template;
    }
}
