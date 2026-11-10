<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $text('admin.pages.title') }}</title>
    @vite('resources/css/admin.css')
</head>
<body>
@include('cms-template-base::system.admin._navbar')
<main class="c-adminshell">
    <h1>{{ $text('admin.pages.title') }}</h1>
    @include('cms-template-base::system.admin._alerts')

    <p><a class="c-adminbutton" href="{{ route('admin.pages.create') }}">{{ $text('admin.pages.create') }}</a></p>

    @if($pages->isEmpty())
        <p class="c-adminshell__lead">{{ $text('admin.pages.empty') }}</p>
    @else
        <div class="c-admintable" role="region" aria-label="{{ $text('admin.pages.title') }}" tabindex="0">
            <table>
                <thead>
                <tr>
                    <th scope="col">{{ $text('admin.pages.id') }}</th>
                    <th scope="col">{{ $text('admin.pages.name') }}</th>
                    <th scope="col">{{ $text('admin.pages.type') }}</th>
                    <th scope="col">{{ $text('admin.pages.home') }}</th>
                    <th scope="col">{{ $text('admin.pages.actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach($pages as $page)
                    <tr>
                        <td>{{ $page->id }}</td>
                        <td>
                            {{ $page->name }}
                            @if(! $page->is_published)
                                <span class="c-adminbadge c--unpublished">{{ $text('admin.pages.unpublished-badge') }}</span>
                            @endif
                        </td>
                        <td>{{ $page->content_type }}</td>
                        <td>
                            @php($pageHomeLocales = collect($homePageIds)->filter(fn ($homePageId) => $homePageId === $page->id)->keys())
                            @if($pageHomeLocales->isNotEmpty())
                                <span class="c-adminbadge-group" role="group" aria-label="{{ $text('admin.pages.home') }}">
                                    @foreach($pageHomeLocales as $languageId)
                                        <span class="c-adminbadge-group__item c-adminbadge c--home">{{ $languages->firstWhere('id', $languageId)?->locale }}</span>
                                    @endforeach
                                </span>
                            @endif
                        </td>
                        <td><a href="{{ route('admin.pages.edit', $page->id) }}">{{ $text('admin.pages.edit') }}</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</main>
</body>
</html>
