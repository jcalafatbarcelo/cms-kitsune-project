<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditEvent;
use App\Services\Admin\AdminAuditLogger;
use App\Services\Admin\AdminPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Modules\Core\Template\Enums\CmsPresentation;

class AuditController extends Controller
{
    public function __invoke(Request $request, AdminPresentation $presentation): Response
    {
        $filters = $request->validate([
            'operation' => ['nullable', 'string', Rule::in(AdminAuditLogger::operations())],
            'entity_type' => ['nullable', 'string', Rule::in(AdminAuditLogger::entityTypes())],
            'from' => ['nullable', 'date'],
            'until' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $perPage = $filters['per_page'] ?? 25;
        $from = $request->date('from');
        $until = $request->date('until');
        $events = AdminAuditEvent::query()
            ->when($filters['operation'] ?? null, fn ($query, string $operation) => $query->where('operation', $operation))
            ->when($filters['entity_type'] ?? null, fn ($query, string $entityType) => $query->where('entity_type', $entityType))
            ->when($from, fn ($query, $from) => $query->where('occurred_at', '>=', $from))
            ->when($until, fn ($query, $until) => $query->where('occurred_at', '<=', $until))
            ->latest('occurred_at')
            ->paginate($perPage)
            ->withQueryString();

        return $presentation->render(CmsPresentation::SystemAdminAudit, data: compact('events', 'filters'));
    }
}
