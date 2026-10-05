<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $text('admin.audit.title') }}</title>
</head>
<body>
<main>
    <h1>{{ $text('admin.audit.title') }}</h1>
    @if ($errors->any())
        <div role="alert">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <form method="GET" action="{{ route('admin.audit') }}">
        <label>{{ $text('admin.audit.operation') }} <input name="operation" value="{{ old('operation', $filters['operation'] ?? '') }}"></label>
        <label>{{ $text('admin.audit.entity') }} <input name="entity_type" value="{{ old('entity_type', $filters['entity_type'] ?? '') }}"></label>
        <label>{{ $text('admin.audit.from') }} <input type="datetime-local" name="from" value="{{ old('from', $filters['from'] ?? '') }}"></label>
        <label>{{ $text('admin.audit.until') }} <input type="datetime-local" name="until" value="{{ old('until', $filters['until'] ?? '') }}"></label>
        <button type="submit">{{ $text('admin.audit.filter') }}</button>
    </form>
    @if ($events->isEmpty())
        <p>{{ $text('admin.audit.empty') }}</p>
    @else
        <table>
            <thead><tr><th>{{ $text('admin.audit.occurred-at') }}</th><th>{{ $text('admin.audit.operation') }}</th><th>{{ $text('admin.audit.entity') }}</th><th>{{ $text('admin.audit.actor') }}</th><th>{{ $text('admin.audit.origin') }}</th><th>{{ $text('admin.audit.before') }}</th><th>{{ $text('admin.audit.after') }}</th></tr></thead>
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
        {{ $events->links() }}
    @endif
</main>
</body>
</html>
