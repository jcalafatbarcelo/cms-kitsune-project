<?php

namespace App\Services\Admin;

use App\Models\AdminAuditEvent;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Support\Facades\App;
use InvalidArgumentException;

class AdminAuditLogger
{
    private const OPERATIONS = [
        'page.create', 'page.translate', 'page.publish', 'page.unpublish', 'page.set_home',
        'menu.create', 'menu.item.create', 'menu.item.update', 'menu.item.move', 'menu.item.remove',
    ];

    private const ENTITY_TYPES = ['page', 'page_translation', 'page_language_home', 'menu', 'menu_item'];

    public function __construct(private readonly Auth $auth) {}

    /** @param array<string, mixed>|null $beforeState
     * @param array<string, mixed>|null $afterState */
    public function record(string $operation, string $entityType, int $entityId, ?array $beforeState, ?array $afterState): void
    {
        if (! in_array($operation, self::OPERATIONS, true) || ! in_array($entityType, self::ENTITY_TYPES, true)) {
            throw new InvalidArgumentException('The audit operation or entity type is invalid.');
        }

        $user = $this->auth->guard('web')->user();
        $actor = $user !== null
            ? ['type' => 'user', 'id' => $user->getAuthIdentifier(), 'label' => 'user:'.$user->getAuthIdentifier(), 'origin' => 'web']
            : (App::runningInConsole()
                ? ['type' => 'console', 'id' => null, 'label' => 'console', 'origin' => 'console']
                : ['type' => 'system', 'id' => null, 'label' => 'system', 'origin' => 'system']);

        AdminAuditEvent::query()->create([
            'occurred_at' => now(),
            'operation' => $operation,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'actor_type' => $actor['type'],
            'actor_id' => $actor['id'],
            'actor_label' => $actor['label'],
            'origin' => $actor['origin'],
            'before_state' => $beforeState,
            'after_state' => $afterState,
            'schema_version' => AdminAuditEvent::SCHEMA_VERSION,
        ]);
    }

    /** @return array<int, string> */
    public static function operations(): array
    {
        return self::OPERATIONS;
    }

    /** @return array<int, string> */
    public static function entityTypes(): array
    {
        return self::ENTITY_TYPES;
    }
}
