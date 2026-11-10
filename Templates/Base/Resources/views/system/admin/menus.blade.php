<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $text('admin.menus.title') }}</title>
    @vite('resources/css/admin.css')
</head>
<body>
@include('cms-template-base::system.admin._navbar')
<main class="c-adminshell">
    <h1>{{ $text('admin.menus.title') }}</h1>
    @include('cms-template-base::system.admin._alerts')
    <p><a class="c-adminbutton" href="{{ route('admin.menus.create') }}">{{ $text('admin.menus.create') }}</a></p>
    @if($menus->isEmpty())
        <p class="c-adminshell__lead">{{ $text('admin.menus.empty') }}</p>
    @else
        <div class="c-admintable" role="region" aria-label="{{ $text('admin.menus.title') }}" tabindex="0">
            <table>
                <thead><tr><th scope="col">{{ $text('admin.menus.identifier') }}</th><th scope="col">{{ $text('admin.pages.actions') }}</th></tr></thead>
                <tbody>
                @foreach($menus as $menu)
                    <tr><td>{{ $menu->identifier }}</td><td><a href="{{ route('admin.menus.show', $menu->id) }}">{{ $text('admin.menus.detail') }}</a></td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</main>
</body>
</html>
