<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $text('admin.audit.title') }}</title>
    @vite('resources/css/admin.css')
</head>
<body>
@include('cms-template-base::system.admin._navbar')
<main class="c-adminshell">
    <h1>{{ $text('admin.audit.title') }}</h1>
    @include('cms-template-base::system.admin._alerts')

    <form class="c-adminform" method="GET" action="{{ route('admin.audit') }}">
        <label for="audit-operation">{{ $text('admin.audit.operation') }}</label>
        <input id="audit-operation" name="operation" value="{{ is_string(old('operation')) ? old('operation') : ($filters['operation'] ?? '') }}">
        <label for="audit-entity-type">{{ $text('admin.audit.entity') }}</label>
        <input id="audit-entity-type" name="entity_type" value="{{ is_string(old('entity_type')) ? old('entity_type') : ($filters['entity_type'] ?? '') }}">
        <label for="audit-from">{{ $text('admin.audit.from') }}</label>
        <input id="audit-from" type="datetime-local" name="from" value="{{ is_string(old('from')) ? old('from') : ($filters['from'] ?? '') }}">
        <label for="audit-until">{{ $text('admin.audit.until') }}</label>
        <input id="audit-until" type="datetime-local" name="until" value="{{ is_string(old('until')) ? old('until') : ($filters['until'] ?? '') }}">
        <button class="c-adminbutton" type="submit">{{ $text('admin.audit.filter') }}</button>
    </form>

    @if ($events->isEmpty())
        <p class="c-adminshell__lead">{{ $text('admin.audit.empty') }}</p>
    @else
        <div class="c-admintable" role="region" aria-label="{{ $text('admin.audit.title') }}" tabindex="0">
            <table>
                <thead>
                <tr>
                    <th scope="col">{{ $text('admin.audit.occurred-at') }}</th>
                    <th scope="col">{{ $text('admin.audit.operation') }}</th>
                    <th scope="col">{{ $text('admin.audit.entity') }}</th>
                    <th scope="col">{{ $text('admin.audit.actor') }}</th>
                    <th scope="col">{{ $text('admin.audit.origin') }}</th>
                    <th scope="col">{{ $text('admin.audit.before') }}</th>
                    <th scope="col">{{ $text('admin.audit.after') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($events as $event)
                    <tr>
                        <td>{{ $event->occurred_at->toIso8601String() }}</td>
                        <td>{{ $event->operation }}</td>
                        <td>{{ $event->entity_type }}:{{ $event->entity_id }}</td>
                        <td>{{ $event->actor_label }}</td>
                        <td>{{ $event->origin }}</td>
                        <td><pre>{{ json_encode($event->before_state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></td>
                        <td><pre>{{ json_encode($event->after_state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $events->links() }}
    @endif
</main>
</body>
</html>
