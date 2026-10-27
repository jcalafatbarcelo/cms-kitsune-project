<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $text('admin.pages.edit') }}</title>
    @if($editor !== null)
        @vite('resources/js/editor.js')
    @endif
</head>
<body>
<main>
    <h1>{{ $text('admin.pages.edit') }} #{{ $page->id }}</h1>
    <p><a href="{{ route('admin.pages.index') }}">{{ $text('admin.pages.title') }}</a></p>

    @if(isset($error))
        <p role="alert">{{ $error }}</p>
    @endif

    @foreach($page->translations as $translation)
        @php($translationLocale = $languages->firstWhere('id', $translation->language_id)?->locale)
        <section>
            <h2>{{ $translationLocale }}</h2>
            <form method="POST" action="{{ route('admin.pages.update', $page->id) }}">
                @csrf
                <input type="hidden" name="locale" value="{{ $translationLocale }}">
                <label for="title-{{ $translation->id }}">{{ $text('admin.pages.heading') }}</label>
                <input id="title-{{ $translation->id }}" name="title" maxlength="255" value="{{ ($input['locale'] ?? null) === $translationLocale ? $input['title'] : $translation->title }}">
                <label for="slug-{{ $translation->id }}">{{ $text('admin.pages.slug') }}</label>
                <input id="slug-{{ $translation->id }}" name="slug" maxlength="100" value="{{ ($input['locale'] ?? null) === $translationLocale ? $input['slug'] : $translation->slug }}">
                @if($editor !== null)
                    <label for="content-{{ $translation->id }}">{{ $text('admin.pages.content') }}</label>
                    <textarea id="content-{{ $translation->id }}" name="content" data-editor="{{ $editor }}" class="js-editor">{{ ($input['locale'] ?? null) === $translationLocale ? ($input['content'] ?? '') : $translation->contents->firstWhere('key', $contentKey)?->content }}</textarea>
                @endif
                <button type="submit">{{ $text('admin.pages.save') }}</button>
            </form>
            <form method="POST" action="{{ route('admin.pages.publish', $page->id) }}">
                @csrf
                <input type="hidden" name="locale" value="{{ $translationLocale }}">
                <button type="submit">{{ $text('admin.pages.publish') }}</button>
            </form>
            <form method="POST" action="{{ route('admin.pages.unpublish', $page->id) }}">
                @csrf
                <input type="hidden" name="locale" value="{{ $translationLocale }}">
                <button type="submit">{{ $text('admin.pages.unpublish') }}</button>
            </form>
        </section>
    @endforeach

    <section>
        <h2>{{ $text('admin.pages.move') }}</h2>
        <form method="POST" action="{{ route('admin.pages.move', $page->id) }}">
            @csrf
            <label for="parent_id">{{ $text('admin.pages.parent') }}</label>
            <select id="parent_id" name="parent_id">
                <option value=""></option>
                @foreach($pages as $candidate)
                    @if($candidate->id !== $page->id)
                        <option value="{{ $candidate->id }}" @if($page->parent_id === $candidate->id) selected @endif>#{{ $candidate->id }}</option>
                    @endif
                @endforeach
            </select>
            <button type="submit">{{ $text('admin.pages.move') }}</button>
        </form>
    </section>

    <section>
        <h2>{{ $text('admin.pages.template') }}</h2>
        <form method="POST" action="{{ route('admin.pages.template', $page->id) }}">
            @csrf
            <label for="template">{{ $text('admin.pages.template') }}</label>
            <select id="template" name="template">
                <option value=""></option>
                @foreach($templates as $template)
                    <option value="{{ $template->identifier }}" @if($page->uses_explicit_template && $page->explicit_template_id === $template->id) selected @endif>{{ $template->name }}</option>
                @endforeach
            </select>
            <button type="submit">{{ $text('admin.pages.save') }}</button>
        </form>
    </section>

    <section>
        <h2>{{ $text('admin.pages.delete') }}</h2>
        <form method="POST" action="{{ route('admin.pages.delete', $page->id) }}">
            @csrf
            <button type="submit">{{ $text('admin.pages.delete') }}</button>
        </form>
    </section>
</main>
</body>
</html>
