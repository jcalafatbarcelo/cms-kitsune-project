<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $text('admin.menus.new') }}</title>
    @vite('resources/css/admin.css')
</head>
<body>
@include('cms-template-base::system.admin._navbar')
<main class="c-adminshell">
    <h1>{{ $text('admin.menus.new') }}</h1>
    @include('cms-template-base::system.admin._alerts')
    <form class="c-adminform" method="POST" action="{{ route('admin.menus.store') }}">
        @csrf
        <label for="identifier">{{ $text('admin.menus.identifier') }}</label>
        <input id="identifier" name="identifier" required maxlength="100" value="{{ is_string(old('identifier')) ? old('identifier') : '' }}">
        <button class="c-adminbutton" type="submit">{{ $text('admin.menus.create') }}</button>
    </form>
</main>
</body>
</html>
