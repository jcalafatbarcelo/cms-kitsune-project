<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $text('admin.dashboard.title') }}</title>
</head>
<body>
<main>
    <h1>{{ $text('admin.dashboard.title') }}</h1>
    @if($errorDetails)
        <p role="alert">{{ $text('admin.diagnostics.warning') }}</p>
    @endif
    <form method="POST" action="{{ route('admin.logout') }}">
        @csrf
        <button type="submit">{{ $text('admin.dashboard.logout') }}</button>
    </form>
</main>
</body>
</html>
