<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $text('admin.pages.new') }}</title>
    @vite('resources/css/admin.css')
</head>
<body>
@include('cms-template-base::system.admin._navbar')
<main class="c-adminshell">
    <h1>{{ $text('admin.pages.new') }}</h1>
    @include('cms-template-base::system.admin._alerts')
    <form class="c-adminform" method="POST" action="{{ route('admin.pages.store') }}">
        @csrf
        <label for="name">{{ $text('admin.pages.name') }}</label>
        <input id="name" name="name" required maxlength="150" value="{{ $input['name'] ?? (is_string(old('name')) ? old('name') : '') }}" aria-describedby="name-help">
        <p id="name-help" class="c-adminform__help">{{ $text('admin.pages.name-help') }}</p>
        <label for="locale">{{ $text('admin.pages.language') }}</label>
        <select id="locale" name="locale">
            @foreach($languages as $language)
                <option value="{{ $language->locale }}" @selected(($input['locale'] ?? old('locale')) === $language->locale)>{{ $language->locale }}</option>
            @endforeach
        </select>
        <label for="slug">{{ $text('admin.pages.slug') }}</label>
        <input id="slug" name="slug" required maxlength="100" value="{{ $input['slug'] ?? (is_string(old('slug')) ? old('slug') : '') }}">
        <label for="title">{{ $text('admin.pages.heading') }}</label>
        <input id="title" name="title" required maxlength="255" value="{{ $input['title'] ?? (is_string(old('title')) ? old('title') : '') }}">
        <label for="type">{{ $text('admin.pages.type') }}</label>
        <select id="type" name="type">
            @foreach($contentTypes as $type)
                <option value="{{ $type->identifier() }}" @selected(($input['type'] ?? old('type')) === $type->identifier())>{{ $type->name() }}</option>
            @endforeach
        </select>
        <label for="template">{{ $text('admin.pages.template') }}</label>
        <select id="template" name="template">
            <option value="">{{ $text('admin.pages.default-template') }}</option>
            @foreach($templates as $template)
                <option value="{{ $template->identifier }}" @selected(($input['template'] ?? old('template')) === $template->identifier)>{{ $template->name }}</option>
            @endforeach
        </select>
        <label for="parent_id">{{ $text('admin.pages.parent') }}</label>
        <select id="parent_id" name="parent_id">
            <option value="">{{ $text('admin.pages.root') }}</option>
            @foreach($pages as $candidate)
                <option value="{{ $candidate->id }}" @selected((string) ($input['parent_id'] ?? old('parent_id')) === (string) $candidate->id)>{{ $candidate->name }}</option>
            @endforeach
        </select>
        <button class="c-adminbutton" type="submit">{{ $text('admin.pages.create') }}</button>
    </form>
</main>
</body>
</html>
