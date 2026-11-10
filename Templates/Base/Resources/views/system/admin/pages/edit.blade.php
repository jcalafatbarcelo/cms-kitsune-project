<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $text('admin.pages.edit') }}</title>
    @vite(['resources/css/admin.css', 'resources/js/admin/page-edit.js'])
</head>
<body>
@include('cms-template-base::system.admin._navbar')
<main class="c-adminshell">
    <h1>{{ $text('admin.pages.edit') }}: {{ $page->name }}</h1>
    @include('cms-template-base::system.admin._alerts')

    @php($translationLocales = $page->translations->mapWithKeys(fn ($translation) => [$translation->language_id => $languages->firstWhere('id', $translation->language_id)?->locale])->filter())
    @php($inputLocale = old('locale'))
    @php($oldTitle = is_string(old('title')) ? old('title') : null)
    @php($oldSlug = is_string(old('slug')) ? old('slug') : null)
    @php($oldContent = is_string(old('content')) ? old('content') : null)
    @php($activeLocale = old('locale', $locale))
    @if(! $translationLocales->contains($activeLocale))
        @php($activeLocale = $translationLocales->first())
    @endif
    <section class="c-adminsection c-admintranslations js-page-edit" data-page-edit data-active-locale="{{ $activeLocale }}">
        @if($languages->count() <= 5)
            <div class="c-admintabs" role="tablist" aria-label="{{ $text('admin.pages.translations') }}">
                @foreach($page->translations as $translation)
                    @php($translationLocale = $languages->firstWhere('id', $translation->language_id)?->locale)
                    @php($languageName = $languages->firstWhere('id', $translation->language_id)?->native_name)
                    <button class="c-admintabs__tab" type="button" role="tab" id="translation-tab-{{ $translation->id }}" aria-controls="translation-panel-{{ $translation->id }}" aria-selected="{{ $translationLocale === $activeLocale ? 'true' : 'false' }}" data-translation-tab="{{ $translationLocale }}">{{ $translationLocale }}{{ $languageName ? ' - '.$languageName : '' }}</button>
                @endforeach
            </div>
        @else
            <div class="c-adminlanguage-selector">
                <label for="translation-search">{{ $text('admin.pages.search-language') }}</label>
                <input id="translation-search" type="search" data-language-search>
                <label for="translation-locale">{{ $text('admin.pages.current-language') }}</label>
                <select id="translation-locale" data-language-select>
                    @foreach($page->translations as $translation)
                        @php($translationLocale = $languages->firstWhere('id', $translation->language_id)?->locale)
                        @php($languageName = $languages->firstWhere('id', $translation->language_id)?->native_name)
                        <option value="{{ $translationLocale }}" @selected($translationLocale === $activeLocale)>{{ $translationLocale }}{{ $languageName ? ' - '.$languageName : '' }}</option>
                    @endforeach
                </select>
            </div>
        @endif

    @foreach($page->translations as $translation)
        @php($translationLocale = $languages->firstWhere('id', $translation->language_id)?->locale)
        @php($languageName = $languages->firstWhere('id', $translation->language_id)?->native_name)
        <section class="c-adminsection c-admintabs__panel c-admintranslation" id="translation-panel-{{ $translation->id }}" @if($languages->count() <= 5) role="tabpanel" aria-labelledby="translation-tab-{{ $translation->id }}" @endif data-translation-panel="{{ $translationLocale }}">
            <h2 class="c-admintranslation__fallback-heading">{{ $languageName ? $languageName.' ('.$translationLocale.')' : $translationLocale }}</h2>
            <span class="c-admintranslation__status c-adminbadge {{ $translation->is_published ? 'c--published' : 'c--unpublished' }}">{{ $text($translation->is_published ? 'admin.pages.published-badge' : 'admin.pages.unpublished-badge') }}</span>
            <form class="c-adminform" method="POST" action="{{ route('admin.pages.update', $page->id) }}">
                @csrf
                <input type="hidden" name="locale" value="{{ $translationLocale }}">
                <label for="title-{{ $translation->id }}">{{ $text('admin.pages.heading') }}</label>
                <input id="title-{{ $translation->id }}" name="title" maxlength="255" value="{{ $inputLocale === $translationLocale ? $oldTitle : $translation->title }}">
                <label for="slug-{{ $translation->id }}">{{ $text('admin.pages.slug') }}</label>
                <input id="slug-{{ $translation->id }}" name="slug" maxlength="100" value="{{ $inputLocale === $translationLocale ? $oldSlug : $translation->slug }}">
                @if($editor !== null)
                    <label for="content-{{ $translation->id }}">{{ $text('admin.pages.content') }}</label>
                    <textarea id="content-{{ $translation->id }}" name="content" data-editor="{{ $editor }}">{{ $inputLocale === $translationLocale ? ($oldContent ?? '') : $translation->contents->firstWhere('key', $contentKey)?->content }}</textarea>
                @endif
                <button class="c-adminbutton" type="submit">{{ $text('admin.pages.save') }}</button>
            </form>
            <div class="c-adminactions">
                <form method="POST" action="{{ route('admin.pages.publish', $page->id) }}">
                    @csrf
                    <input type="hidden" name="locale" value="{{ $translationLocale }}">
                    <button class="c-adminbutton" type="submit">{{ $text('admin.pages.publish') }}</button>
                </form>
                <form method="POST" action="{{ route('admin.pages.unpublish', $page->id) }}">
                    @csrf
                    <input type="hidden" name="locale" value="{{ $translationLocale }}">
                    <button class="c-adminbutton c--secondary" type="submit">{{ $text('admin.pages.unpublish') }}</button>
                </form>
            </div>
        </section>
    @endforeach
    </section>

    <section class="c-adminsection">
        <h2>{{ $text('admin.pages.move') }}</h2>
        <form class="c-adminform" method="POST" action="{{ route('admin.pages.move', $page->id) }}">
            @csrf
            <label for="parent_id">{{ $text('admin.pages.parent') }}</label>
            <select id="parent_id" name="parent_id">
                <option value="">{{ $text('admin.pages.root') }}</option>
                @foreach($moveCandidates as $candidate)
                    <option value="{{ $candidate->id }}" @if($page->parent_id === $candidate->id) selected @endif>{{ $candidate->name }}</option>
                @endforeach
            </select>
            <button class="c-adminbutton" type="submit">{{ $text('admin.pages.move') }}</button>
        </form>
    </section>

    <section class="c-adminsection">
        <h2>{{ $text('admin.pages.template') }}</h2>
        <form class="c-adminform" method="POST" action="{{ route('admin.pages.template', $page->id) }}">
            @csrf
            <label for="template">{{ $text('admin.pages.template') }}</label>
            <select id="template" name="template">
                <option value="">{{ $text('admin.pages.default-template') }}</option>
                @foreach($templates as $template)
                    <option value="{{ $template->identifier }}" @if($page->uses_explicit_template && $page->explicit_template_id === $template->id) selected @endif>{{ $template->name }}</option>
                @endforeach
            </select>
            <button class="c-adminbutton" type="submit">{{ $text('admin.pages.save') }}</button>
        </form>
    </section>

    <section class="c-adminsection">
        <h2>{{ $text('admin.pages.delete') }}</h2>
        <div class="c-adminactions">
            <form method="POST" action="{{ route('admin.pages.delete', $page->id) }}">
                @csrf
                <button class="c-adminbutton c--danger" type="submit">{{ $text('admin.pages.delete') }}</button>
            </form>
        </div>
    </section>
</main>
</body>
</html>
