<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $text('admin.dashboard.title') }}</title>
    @vite('resources/css/admin.css')
</head>
<body>
@include('cms-template-base::system.admin._navbar')
<main class="c-adminshell">
    <h1>{{ $text('admin.dashboard.title') }}</h1>
    <p class="c-adminshell__lead">{{ $text('admin.dashboard.lead') }}</p>
    @include('cms-template-base::system.admin._alerts')
    @if($errorDetails)
        <p class="c-adminalert c--warning" role="alert">{{ $text('admin.diagnostics.warning') }}</p>
    @endif
</main>
</body>
</html>
