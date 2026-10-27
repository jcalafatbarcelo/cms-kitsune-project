<!doctype html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $translation->title }}</title>
</head>
<body>
<main>
    @if($content !== null)
        <article>{!! $content !!}</article>
    @else
        <h1>{{ $templateUi->text($effectiveTemplate, $presentationTemplate, 'page.home.under-construction.heading', $locale) }}</h1>
        <p>{{ $templateUi->text($effectiveTemplate, $presentationTemplate, 'page.home.under-construction.message', $locale) }}</p>
    @endif
</main>
</body>
</html>
