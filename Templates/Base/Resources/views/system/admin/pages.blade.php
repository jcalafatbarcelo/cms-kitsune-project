<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $text('admin.pages.title') }}</title>
</head>
<body>
<main>
    <h1>{{ $text('admin.pages.title') }}</h1>
    <p><a href="{{ route('admin.dashboard') }}">{{ $text('admin.dashboard.title') }}</a> · <a href="{{ route('admin.menus.index') }}">{{ $text('admin.menus.title') }}</a></p>

    @if(isset($error))
        <p role="alert">{{ $error }}</p>
    @endif

    <h2>{{ $text('admin.pages.create') }}</h2>
    <form method="POST" action="{{ route('admin.pages.store') }}">
        @csrf
        <label for="locale">{{ $text('admin.pages.language') }}</label>
        <select id="locale" name="locale">
            @foreach($languages as $language)
                <option value="{{ $language->locale }}">{{ $language->locale }}</option>
            @endforeach
        </select>
        <label for="slug">{{ $text('admin.pages.slug') }}</label>
        <input id="slug" name="slug" required maxlength="100" value="{{ $input['slug'] ?? '' }}">
        <label for="title">{{ $text('admin.pages.heading') }}</label>
        <input id="title" name="title" required maxlength="255" value="{{ $input['title'] ?? '' }}">
        <label for="type">{{ $text('admin.pages.type') }}</label>
        <select id="type" name="type">
            @foreach($contentTypes as $type)
                <option value="{{ $type->identifier() }}">{{ $type->name() }}</option>
            @endforeach
        </select>
        <label for="template">{{ $text('admin.pages.template') }}</label>
        <select id="template" name="template">
            <option value=""></option>
            @foreach($templates as $template)
                <option value="{{ $template->identifier }}">{{ $template->name }}</option>
            @endforeach
        </select>
        <label for="parent_id">{{ $text('admin.pages.parent') }}</label>
        <select id="parent_id" name="parent_id">
            <option value=""></option>
            @foreach($pages as $candidate)
                <option value="{{ $candidate->id }}">#{{ $candidate->id }}</option>
            @endforeach
        </select>
        <button type="submit">{{ $text('admin.pages.create') }}</button>
    </form>

    @if($pages->isEmpty())
        <p>{{ $text('admin.pages.empty') }}</p>
    @else
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>{{ $text('admin.pages.type') }}</th>
                <th>{{ $text('admin.pages.home') }}</th>
                <th>{{ $text('admin.pages.actions') }}</th>
            </tr>
            </thead>
            <tbody>
            @foreach($pages as $page)
                <tr>
                    <td>{{ $page->id }}</td>
                    <td>{{ $page->content_type }}</td>
                    <td>
                        @foreach($homePageIds as $languageId => $homePageId)
                            @if($homePageId === $page->id)
                                {{ $languages->firstWhere('id', $languageId)?->locale }}
                            @endif
                        @endforeach
                    </td>
                    <td><a href="{{ route('admin.pages.edit', $page->id) }}">{{ $text('admin.pages.edit') }}</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</main>
</body>
</html>
