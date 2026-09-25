<!doctype html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $translation->title }}</title>
</head>
<body>
<main>
    <h1>{{ $templateUi->text($template, 'page.home.under-construction.heading', $locale) }}</h1>
    <p>{{ $templateUi->text($template, 'page.home.under-construction.message', $locale) }}</p>
</main>
</body>
</html>
