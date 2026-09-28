<nav aria-label="{{ $templateUi->text($effectiveTemplate, $presentationTemplate, 'navigation.menu.label', $locale) }}">
    @include('cms-template-base::public.navigation.items', ['items' => $items])
</nav>
